<?php

/**
 * Service GapAnalysisService — Moteur de detection d'ecarts (version optimisee).
 *
 * Pipeline :
 *   1. Pour chaque exigence (chunk de referentiel), cherche la meilleure preuve
 *      dans les chunks des documents client (RAG pgvector ou fallback full-text).
 *   2. Le score RAG suffit a trancher :
 *        - score >= SEUIL_PREUVE_SUFFISANTE   -> conforme (pas d'appel LLM, pas d'ecart)
 *        - score >= SEUIL_PREUVE_PARTIELLE    -> ecart "preuve_insuffisante"
 *        - sinon                              -> ecart "absence_totale"
 *   3. Le LLM n'est appele que pour REDIGER l'ecart (titre + description + reco),
 *      pas pour juger la conformite (coute supprime = 50% des appels LLM en moins).
 *   4. La progression (nb traites, pct, etape) est persistee a chaque exigence
 *      pour suivi UI temps reel.
 */

namespace App\Services\Analyse;

use App\Contracts\LLMConnectorInterface;
use App\Models\Analyse;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\Ecart;
use App\Models\QuestionnaireGenere;
use App\Models\ReferentielChunk;
use App\Services\RAG\PgvectorChecker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Pgvector\Laravel\Distance;

class GapAnalysisService
{
    // Seuils calibres pour nomic-embed-text (768 dims, modele dedie embeddings).
    // Les scores de ce modele sur du texte francais juridique sont generalement
    // plus eleves qu'avec un LLM chat utilise comme embedding -> seuils ajustes.
    //   - >= 0.50 : conforme (la Convention SUNU sur sous-traitance atteint 0.55-0.70)
    //   - >= 0.32 : preuve insuffisante (matching pertinent mais incomplet)
    //   - >= 0.20 : ecart absence_totale (le doc effleure le sujet)
    //   - <  0.20 : hors perimetre (le doc ne parle pas du sujet)
    private const SEUIL_PREUVE_SUFFISANTE = 0.50;
    private const SEUIL_PREUVE_PARTIELLE = 0.32;
    private const SEUIL_HORS_PERIMETRE = 0.20;

    // Seuils plus permissifs pour les Documents miroirs de questionnaire :
    // sans pgvector, le scoring fulltext est faible (ts_rank * 10), et les
    // reponses du client utilisent un vocabulaire eloigne des exigences
    // ARTCI. On baisse les seuils pour eviter que les questionnaires ne
    // soient systematiquement marques "hors_perimetre".
    private const SEUIL_QUESTIONNAIRE_SUFFISANTE = 0.30;
    private const SEUIL_QUESTIONNAIRE_PARTIELLE = 0.10;
    private const SEUIL_QUESTIONNAIRE_HORS_PERIMETRE = 0.02;

    // Circuit-breaker LLM : apres N echecs consecutifs, on bascule sur les
    // templates pour le reste de l'analyse (evite 60 timeouts si Ollama meurt
    // en cours de route). Releve de 3 a 5 : llama3.2:3b peut occasionellement
    // produire un JSON malforme sur un prompt complexe, sans pour autant que
    // tout le service soit casse.
    private const MAX_ECHECS_LLM_CONSECUTIFS = 5;

    private bool $llmActif = false;
    private int $echecsLlmConsecutifs = 0;

    public function __construct(
        private LLMConnectorInterface $llm,
        private PgvectorChecker $pgvectorChecker,
    ) {}

    public function executer(Analyse $analyse): void
    {
        $analyse->update([
            'statut' => 'en_cours',
            'demarree_a' => now(),
            'etape_courante' => 'Chargement des exigences',
            'progression_pct' => 0,
        ]);

        // L'analyse "rapide" doit l'etre vraiment : on FORCE l'usage des
        // templates (pas d'appel LLM) — chaque appel a llama3.2:3b sur CPU
        // prend 5-15s, multiplie par N ecarts ca fait largement >30s. Le LLM
        // n'est utilise que si l'utilisateur a explicitement coche
        // "Enrichissement IA" lors de la creation de l'analyse.
        $modeIa = (bool) $analyse->enrichissement_ia;
        $this->llmActif = $modeIa ? $this->verifierLlmReady($analyse->id) : false;
        $this->echecsLlmConsecutifs = 0;

        try {
            $referentielsIds = $analyse->referentiels_ids ?? [];
            $documentsIds = $analyse->documents_ids ?? [];
            $questionnairesIds = $analyse->questionnaires_ids ?? [];

            // Materialise chaque formulaire renseigne en Document + chunks indexes
            // afin que le moteur RAG puisse les exploiter au meme titre que les PDF/DOCX
            // uploades par le client.
            if (! empty($questionnairesIds)) {
                $documentsIssusFormulaires = $this->materialiserQuestionnaires($questionnairesIds);
                $documentsIds = array_values(array_unique(array_merge($documentsIds, $documentsIssusFormulaires)));
                if ($documentsIds !== ($analyse->documents_ids ?? [])) {
                    $analyse->update(['documents_ids' => $documentsIds]);
                }
            }

            // Filet de securite : la matrice de collecte et les questionnaires
            // ne sont plus obligatoires. Si aucun document n'est attache a
            // l'analyse, on prend tous les documents indexes du client de la
            // mission pour permettre le lancement quand-meme.
            if (empty($documentsIds) && $analyse->mission && $analyse->mission->client_id) {
                $documentsIds = Document::query()
                    ->whereHas('mission', fn ($q) => $q->where('client_id', $analyse->mission->client_id))
                    ->pluck('id')
                    ->all();
                if (! empty($documentsIds)) {
                    $analyse->update(['documents_ids' => $documentsIds]);
                }
            }

            if (empty($referentielsIds)) {
                throw new \RuntimeException('Aucun referentiel selectionne pour cette analyse.');
            }
            if (empty($documentsIds)) {
                throw new \RuntimeException(
                    'Aucun document client disponible : importez au moins un document avant de lancer l\'analyse.'
                );
            }

            // Verifier que la table document_chunks existe (pgvector install)
            if (! Schema::hasTable('document_chunks')) {
                throw new \RuntimeException('Table document_chunks absente — lancer les migrations.');
            }

            $exigences = ReferentielChunk::whereIn('referentiel_id', $referentielsIds)
                ->orderBy('referentiel_id')
                ->orderBy('position')
                ->get();

            if ($exigences->isEmpty()) {
                throw new \RuntimeException(
                    'Aucune exigence indexee. Uploadez les fichiers PDF des referentiels et attendez leur indexation (chunks_count > 0 dans la liste).'
                );
            }

            // Verifier que les documents client ont ete indexes
            $nbChunksDocs = DocumentChunk::whereIn('document_id', $documentsIds)->count();
            if ($nbChunksDocs === 0) {
                throw new \RuntimeException(
                    'Aucun chunk trouve pour les documents client. Attendez la fin de l\'indexation (statut "indexe" sur chaque document).'
                );
            }

            $nbTotal = $exigences->count();
            $documents = Document::whereIn('id', $documentsIds)->get();
            $nbDocs = $documents->count();
            $totalEvaluations = $nbTotal * $nbDocs;

            $analyse->update([
                'nb_exigences_total' => $nbTotal,
                'etape_courante' => "Analyse de {$nbTotal} exigences sur {$nbDocs} documents ({$totalEvaluations} evaluations)",
            ]);

            // Etape 1 : evaluation par (document x exigence), collecte des evidences
            // et statut de conformite par document.
            $evidencesParExigence = []; // [exigence_id => [{document, type_ecart, score, extrait, metadata}]]
            $conformesParExigence = []; // [exigence_id => true] si au moins 1 doc la couvre
            $conformiteParDoc = [];
            $compteur = 0;

            foreach ($documents as $document) {
                $ecartsDoc = 0;
                $inPerimetre = 0;
                $conformes = 0;

                foreach ($exigences as $exigence) {
                    $compteur++;
                    $verdict = $this->evaluerExigenceDansDocument($exigence, $document->id);

                    if ($verdict['statut'] === 'hors_perimetre') {
                        continue;
                    }

                    $inPerimetre++;

                    if ($verdict['statut'] === 'conforme') {
                        $conformes++;
                        // Tracer qu'au moins UN doc couvre cette exigence
                        $conformesParExigence[$exigence->id] = true;
                    } else {
                        $ecartsDoc++;
                        $evidencesParExigence[$exigence->id][] = $verdict + ['document' => $document];
                    }

                    if ($compteur % 10 === 0 || $compteur === $totalEvaluations) {
                        $pct = (int) round(($compteur / $totalEvaluations) * 90); // 0-90% pour l'evaluation
                        $analyse->update([
                            'nb_exigences_verifiees' => $compteur,
                            'progression_pct' => $pct,
                            'etape_courante' => "Analyse : {$document->titre} ({$compteur}/{$totalEvaluations})",
                        ]);
                    }
                }

                $conformiteParDoc[] = [
                    'document_id' => $document->id,
                    'titre' => $document->titre,
                    'nom_fichier' => $document->nom_fichier_original,
                    'statut' => $ecartsDoc === 0 && $inPerimetre > 0 ? 'conforme'
                        : ($inPerimetre === 0 ? 'non_evalue' : 'non_conforme'),
                    'nb_ecarts' => $ecartsDoc,
                    'exigences_dans_perimetre' => $inPerimetre,
                    'exigences_conformes' => $conformes,
                ];
            }

            // Etape 2 : creer UN ecart par exigence en consolidant les documents qui l'enfreignent.
            $analyse->update([
                'etape_courante' => 'Consolidation des ecarts par exigence',
                'progression_pct' => 92,
            ]);

            $nbCritiques = 0;
            $nbMajeurs = 0;
            $nbMineurs = 0;

            foreach ($evidencesParExigence as $exigenceId => $evidences) {
                $exigence = $exigences->firstWhere('id', $exigenceId);
                if (! $exigence) {
                    continue;
                }
                $ecart = $this->creerEcartAgrege($analyse, $exigence, $evidences);
                match ($ecart->gravite) {
                    'critique' => $nbCritiques++,
                    'majeur' => $nbMajeurs++,
                    'mineur' => $nbMineurs++,
                    default => null,
                };
            }

            // Score metier : % d'exigences couvertes par au moins un document client,
            // pondere par la gravite de l'exigence.
            // (L'ancien calcul "% evaluations (doc x exigence) conformes" donnait des
            // scores trompeurs : 8 docs x 60 exigences = 480 evaluations, dont une
            // ecrasante majorite finissait en absence_totale par construction.)
            $score = $this->calculerScoreConformite($exigences, $conformesParExigence);

            $analyse->update([
                'etape_courante' => 'Generation de la synthese',
                'progression_pct' => 95,
            ]);

            $analyse->update([
                'statut' => 'terminee',
                'terminee_a' => now(),
                'nb_exigences_verifiees' => $nbTotal,
                'nb_ecarts_critiques' => $nbCritiques,
                'nb_ecarts_majeurs' => $nbMajeurs,
                'nb_ecarts_mineurs' => $nbMineurs,
                'score_conformite' => $score,
                'synthese' => $this->construireSynthese($analyse, $conformiteParDoc),
                'commentaire_ia' => $this->genererCommentaireSynthese($analyse),
                'progression_pct' => 100,
                'etape_courante' => 'Analyse terminee',
                // Marquer l'analyse comme enrichie IA si le LLM a ete utilise
                // (cache le bouton "Enrichir IA" dans l'UI car deja fait).
                'enrichissement_ia' => $this->llmActif || $this->echecsLlmConsecutifs > 0,
            ]);
        } catch (\Throwable $e) {
            Log::error("Analyse {$analyse->id} echouee : {$e->getMessage()}");
            $analyse->update([
                'statut' => 'erreur',
                'erreur_message' => $e->getMessage(),
                'terminee_a' => now(),
                'etape_courante' => 'Erreur',
            ]);
            throw $e;
        }
    }

    /**
     * Convertit chaque QuestionnaireGenere en Document + DocumentChunks
     * exploitables par le moteur d'analyse. Idempotent : si le Document
     * miroir existe deja, on le rafraichit avec les dernieres reponses.
     *
     * @param  array<int>  $questionnairesIds
     * @return array<int>  Liste des Document IDs crees ou mis a jour
     */
    private function materialiserQuestionnaires(array $questionnairesIds): array
    {
        $questionnaires = QuestionnaireGenere::whereIn('id', $questionnairesIds)->get();
        $documentsIds = [];

        foreach ($questionnaires as $q) {
            $questions = $q->questions ?? [];
            $reponsesParNumero = collect($q->reponses ?? [])->keyBy('numero');

            // Construit un texte synthese du formulaire renseigne
            $bloc = "Formulaire : {$q->titre}\n";
            if ($q->description) {
                $bloc .= "Description : {$q->description}\n";
            }
            $bloc .= "Pole : {$q->pole}" . ($q->service ? " / {$q->service}" : '') . "\n\n";

            $chunks = [];
            foreach ($questions as $qu) {
                $numero = $qu['numero'] ?? 0;
                $texte = trim($qu['texte'] ?? '');
                $rep = $reponsesParNumero->get($numero);
                $reponse = trim($rep['reponse'] ?? '');
                $repondu = ! empty($reponse);

                $contenu = "Question {$numero} : {$texte}\nReponse : " . ($repondu ? $reponse : '(non repondu)');
                $bloc .= $contenu . "\n\n";

                if (mb_strlen(trim($contenu)) >= 20) {
                    $chunks[] = [
                        'contenu' => $contenu,
                        'metadata' => [
                            'is_questionnaire' => true,
                            'questionnaire_id' => $q->id,
                            'question_numero' => $numero,
                            'question_texte' => $this->tronquerSurMot($texte, 500),
                            'reponse_texte' => $this->tronquerSurMot($reponse, 500),
                            'repondu' => $repondu,
                        ],
                    ];
                }
            }

            // Cherche un Document miroir deja cree pour ce questionnaire
            $document = Document::where('mission_id', $q->mission_id)
                ->where('type', 'questionnaire_synthese')
                ->where('hash_fichier', 'questionnaire-' . $q->id)
                ->first();

            // Hash du contenu pour determiner si on doit ou non re-indexer.
            // Si le bloc texte est identique au precedent, on saute toute la
            // re-indexation (et donc les ~30s d'embeddings).
            $hashContenu = hash('sha256', $bloc);

            if (! $document) {
                $document = Document::create([
                    'mission_id' => $q->mission_id,
                    'uploaded_by' => $q->rempli_par ?? $q->genere_par,
                    'titre' => "[Formulaire] {$q->titre}",
                    'description' => 'Synthese automatique des reponses du formulaire pour analyse d\'ecarts.',
                    'nom_fichier_original' => 'questionnaire-' . $q->id . '.txt',
                    'type_mime' => 'text/plain',
                    'taille_octets' => mb_strlen($bloc),
                    'type' => 'questionnaire_synthese',
                    'statut' => 'indexe',
                    'is_confidentiel' => true,
                    'hash_fichier' => 'questionnaire-' . $q->id,
                    'contenu_extrait' => $bloc,
                    'metadata' => ['contenu_sha' => $hashContenu],
                ]);
            } else {
                $shaPrec = $document->metadata['contenu_sha'] ?? null;
                $contenuInchange = $shaPrec === $hashContenu
                    && DocumentChunk::where('document_id', $document->id)->exists();

                if ($contenuInchange) {
                    // Reponses du formulaire identiques au dernier passage :
                    // on reutilise tels quels les chunks (+ embeddings) deja en base.
                    $documentsIds[] = $document->id;
                    continue;
                }

                $document->update([
                    'taille_octets' => mb_strlen($bloc),
                    'contenu_extrait' => $bloc,
                    'statut' => 'indexe',
                    'metadata' => array_merge($document->metadata ?? [], ['contenu_sha' => $hashContenu]),
                ]);
                // Reponses modifiees : on purge les anciens chunks pour reindexer.
                DocumentChunk::where('document_id', $document->id)->delete();
            }

            // Indexation des chunks avec embeddings pour permettre la recherche
            // pgvector (nomic-embed-text est rapide : ~0.1-0.3s par chunk sur CPU).
            // Sans embedding, la recherche pgvector des exigences ne renvoie rien
            // pour ce document et le fallback fulltext rate beaucoup de matches a
            // cause du vocabulaire different entre exigences ARTCI et reponses
            // client (cf. bug : 4/54 ecarts seulement avec evidence questionnaire).
            $pgvectorDispo = $this->pgvectorChecker->estDisponible()
                && filter_var(config('services.ollama.embeddings_enabled', true), FILTER_VALIDATE_BOOL);

            foreach ($chunks as $i => $chunk) {
                $donnees = [
                    'document_id' => $document->id,
                    'contenu' => $chunk['contenu'],
                    'position' => $i,
                    'page' => null,
                    'taille_caracteres' => mb_strlen($chunk['contenu']),
                    'metadata' => $chunk['metadata'] ?? null,
                ];

                if ($pgvectorDispo) {
                    try {
                        $donnees['embedding'] = $this->llm->genererEmbedding($chunk['contenu']);
                    } catch (\Throwable $e) {
                        Log::warning("materialiserQuestionnaires : embedding echoue pour Q#{$q->id} chunk $i", [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                DocumentChunk::create($donnees);
            }

            $documentsIds[] = $document->id;
        }

        return $documentsIds;
    }

    /**
     * Evalue une exigence pour UN document specifique.
     * Retourne un statut :
     *   - 'hors_perimetre' : le document ne traite pas du tout cette exigence (score < 0.15)
     *   - 'conforme'       : le document couvre l'exigence (score >= 0.55)
     *   - 'ecart'          : preuve_insuffisante ou absence_totale
     */
    private function evaluerExigenceDansDocument(ReferentielChunk $exigence, int $documentId): array
    {
        $preuve = $this->rechercherPreuveDansDocument($exigence, $documentId);
        $score = $preuve['score'] ?? 0;

        // Seuils adaptes selon le type de document : les questionnaires
        // (reponses du client) sont scores plus indulgemment en fulltext car
        // leur vocabulaire est volontairement different des exigences.
        $estQuestionnaire = $this->estDocumentQuestionnaire($documentId);
        $seuilHors = $estQuestionnaire ? self::SEUIL_QUESTIONNAIRE_HORS_PERIMETRE : self::SEUIL_HORS_PERIMETRE;
        $seuilSuffisante = $estQuestionnaire ? self::SEUIL_QUESTIONNAIRE_SUFFISANTE : self::SEUIL_PREUVE_SUFFISANTE;
        $seuilPartielle = $estQuestionnaire ? self::SEUIL_QUESTIONNAIRE_PARTIELLE : self::SEUIL_PREUVE_PARTIELLE;

        if ($score < $seuilHors) {
            return ['statut' => 'hors_perimetre', 'score' => $score];
        }

        if ($score >= $seuilSuffisante) {
            return ['statut' => 'conforme', 'score' => $score];
        }

        $typeEcart = $score >= $seuilPartielle
            ? 'preuve_insuffisante'
            : 'absence_totale';

        // Pour absence_totale (score 0.15-0.30) le chunk trouve n'est PAS une preuve
        // — c'est juste le moins mauvais match dans un document qui n'aborde pas
        // vraiment le sujet. On l'evacue pour ne pas afficher un extrait hors-sujet
        // qui fait paraitre l'ecart incoherent.
        // EXCEPTION : pour les questionnaires, on conserve toujours l'extrait
        // (la question + reponse du client est une evidence qualitative en soi).
        $extrait = ($typeEcart === 'absence_totale' && ! $estQuestionnaire) ? null : ($preuve['contenu'] ?? null);
        $meta = ($typeEcart === 'absence_totale' && ! $estQuestionnaire) ? null : ($preuve['metadata'] ?? null);

        return [
            'statut' => 'ecart',
            'type_ecart' => $typeEcart,
            'gravite' => $this->determinerGravite($exigence, $typeEcart),
            'score' => $score,
            'document_id' => $documentId,
            'extrait_document' => $extrait,
            'metadata' => $meta,
        ];
    }

    /**
     * Cache simple en memoire pour eviter de re-fetch le type a chaque exigence
     * (appele ~200 exigences x 11 docs = 2200 fois par analyse).
     */
    private array $typeDocCache = [];
    private function estDocumentQuestionnaire(int $documentId): bool
    {
        if (! isset($this->typeDocCache[$documentId])) {
            $type = Document::where('id', $documentId)->value('type');
            $this->typeDocCache[$documentId] = ($type === 'questionnaire_synthese');
        }

        return $this->typeDocCache[$documentId];
    }

    private function rechercherPreuveDansDocument(ReferentielChunk $exigence, int $documentId): ?array
    {
        if ($this->pgvectorChecker->estDisponible() && $exigence->embedding) {
            try {
                $chunk = DocumentChunk::query()
                    ->nearestNeighbors('embedding', $exigence->embedding, Distance::Cosine)
                    ->where('document_id', $documentId)
                    ->select(['document_chunks.id', 'document_chunks.document_id', 'document_chunks.contenu', 'document_chunks.embedding', 'document_chunks.metadata'])
                    ->first();

                if ($chunk && $chunk->embedding) {
                    return [
                        'document_id' => $chunk->document_id,
                        'contenu' => $this->tronquerSurMot($chunk->contenu, 1500),
                        'score' => $this->calculerScoreCosinus($exigence->embedding, $chunk->embedding),
                        'metadata' => is_string($chunk->metadata) ? json_decode($chunk->metadata, true) : $chunk->metadata,
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning("Recherche vectorielle echouee pour exigence {$exigence->id} doc {$documentId}", ['error' => $e->getMessage()]);
            }
        }

        return $this->rechercheFullText($exigence, [$documentId]);
    }

    /**
     * Fallback full-text base sur mots-cles — evite de faire un CALL Ollama par exigence pour l'embedding.
     */
    private function rechercheFullText(ReferentielChunk $exigence, array $documentsIds): ?array
    {
        $mots = $this->extraireMotsCles($exigence->contenu);
        if (empty($mots)) {
            return null;
        }

        // Purge les tokens dangereux pour to_tsquery et use OR (|) pour tolerer
        // les textes legerement degrades (OCR, DOCX casses).
        $tokens = [];
        foreach ($mots as $m) {
            $t = preg_replace('/[^\p{L}\p{N}]+/u', '', $m);
            if ($t !== '' && mb_strlen($t) > 3) {
                $tokens[] = $t;
            }
        }
        if (empty($tokens)) {
            return null;
        }
        $requeteOr = implode(' | ', $tokens);
        $pdo = DB::getPdo();
        $quotee = $pdo->quote($requeteOr);

        $chunk = DB::table('document_chunks')
            ->whereIn('document_id', $documentsIds)
            ->whereRaw("to_tsvector('french', contenu) @@ to_tsquery('french', ?)", [$requeteOr])
            ->select('document_id', 'contenu', 'metadata', DB::raw("ts_rank(to_tsvector('french', contenu), to_tsquery('french', {$quotee})) as score"))
            ->orderByDesc('score')
            ->first();

        if (! $chunk) {
            return null;
        }

        // Normaliser le score ts_rank (typiquement 0-0.2) en echelle cosinus
        $scoreNormalise = min(1.0, (float) $chunk->score * 10);

        return [
            'document_id' => $chunk->document_id,
            'contenu' => $this->tronquerSurMot($chunk->contenu, 1500),
            'score' => round($scoreNormalise, 4),
            'metadata' => is_string($chunk->metadata) ? json_decode($chunk->metadata, true) : $chunk->metadata,
        ];
    }

    /**
     * Tronque un texte sur frontiere de mot avec ellipsis si coupure.
     * Evite les "des do" / "latifs au traitement" dans l'UI.
     */
    private function tronquerSurMot(string $texte, int $maxLen): string
    {
        if (mb_strlen($texte) <= $maxLen) {
            return $texte;
        }
        $coupe = mb_substr($texte, 0, $maxLen);
        $dernierEspace = mb_strrpos($coupe, ' ');
        if ($dernierEspace !== false && $dernierEspace > $maxLen * 0.7) {
            return rtrim(mb_substr($coupe, 0, $dernierEspace)) . '…';
        }

        return $coupe . '…';
    }

    private function calculerScoreCosinus($vec1, $vec2): float
    {
        $a = is_object($vec1) && method_exists($vec1, 'toArray') ? $vec1->toArray() : (array) $vec1;
        $b = is_object($vec2) && method_exists($vec2, 'toArray') ? $vec2->toArray() : (array) $vec2;

        if (empty($a) || empty($b) || count($a) !== count($b)) {
            return 0.0;
        }

        $dot = 0;
        $normA = 0;
        $normB = 0;
        $n = count($a);
        for ($i = 0; $i < $n; $i++) {
            $dot += $a[$i] * $b[$i];
            $normA += $a[$i] ** 2;
            $normB += $b[$i] ** 2;
        }

        if ($normA == 0 || $normB == 0) {
            return 0.0;
        }

        return round($dot / (sqrt($normA) * sqrt($normB)), 4);
    }

    /**
     * Cree UN ecart par exigence en agregeant les documents qui presentent le manquement.
     *
     * Deduplication : si l'exigence "conservation des donnees" est manquante dans 3 documents,
     * on cree 1 seul ecart avec documents_sources = [doc1, doc2, doc3] au lieu de 3 ecarts.
     *
     * @param  array<int, array{type_ecart:string,gravite:string,score:float,extrait_document:?string,metadata:?array,document:\App\Models\Document}>  $evidences
     */
    private function creerEcartAgrege(Analyse $analyse, ReferentielChunk $exigence, array $evidences): Ecart
    {
        // Type d'ecart agrege : le pire l'emporte (absence_totale > preuve_insuffisante)
        $typeAgrege = collect($evidences)->contains(fn ($v) => $v['type_ecart'] === 'absence_totale')
            ? 'absence_totale'
            : 'preuve_insuffisante';

        // Evidence "primaire" pour la retro-compat (rapports Word/PPTX) et la redaction LLM :
        // celle avec le meilleur score (la plus parlante)
        $primaire = collect($evidences)->sortByDesc('score')->first();
        $gravitePrimaire = $primaire['gravite'];

        $verdictPourRedaction = [
            'type_ecart' => $typeAgrege,
            'gravite' => $gravitePrimaire,
            'score' => $primaire['score'],
            'document_id' => $primaire['document']->id,
            'extrait_document' => $primaire['extrait_document'],
            'metadata' => $primaire['metadata'] ?? null,
        ];

        // Toujours tenter le LLM si Ollama est joignable, fallback template sinon.
        // L'ancien flag `enrichissement_ia` n'est plus utilise pour decider ici :
        // par defaut, l'analyse rapide rédige les écarts via Ollama.
        if ($this->llmActif) {
            [$description, $risque, $recommandation, $titre, $llmOk] = $this->redigerEcartAvecLlmSafe($exigence, $verdictPourRedaction);
            if ($llmOk) {
                $this->echecsLlmConsecutifs = 0;
            } else {
                $this->echecsLlmConsecutifs++;
                if ($this->echecsLlmConsecutifs >= self::MAX_ECHECS_LLM_CONSECUTIFS) {
                    Log::warning("Analyse {$analyse->id} : {$this->echecsLlmConsecutifs} echecs LLM consecutifs, bascule definitive sur templates.");
                    $this->llmActif = false;
                }
            }
        } else {
            [$description, $risque, $recommandation, $titre] = $this->redactionFallback($exigence, $typeAgrege);
        }

        // Construire le tableau documents_sources : un par document concerne
        $documentsSources = [];
        foreach ($evidences as $ev) {
            $doc = $ev['document'];
            $meta = is_string($ev['metadata'] ?? null)
                ? json_decode($ev['metadata'], true)
                : ($ev['metadata'] ?? null);

            $questionNumero = null;
            $questionTexte = null;
            $reponseClient = null;
            if (is_array($meta) && ($meta['is_questionnaire'] ?? false)) {
                $questionNumero = $meta['question_numero'] ?? null;
                $questionTexte = $meta['question_texte'] ?? null;
                $reponseClient = ($meta['repondu'] ?? false)
                    ? ($meta['reponse_texte'] ?? null)
                    : '(Question non repondue)';
            }

            $documentsSources[] = [
                'document_id' => $doc->id,
                'titre' => $doc->titre,
                'nom_fichier' => $doc->nom_fichier_original,
                'type_ecart' => $ev['type_ecart'],
                'score_similarite' => round((float) $ev['score'], 4),
                'extrait_document' => $ev['extrait_document'],
                'question_numero' => $questionNumero,
                'question_texte' => $questionTexte,
                'reponse_client' => $reponseClient,
            ];
        }

        // Tri : meilleur score d'abord (le document le plus pertinent en tete)
        usort($documentsSources, fn ($a, $b) => ($b['score_similarite'] ?? 0) <=> ($a['score_similarite'] ?? 0));

        // Champs "primaire" pour retro-compat (rapports Word/PPTX qui ne connaissent pas documents_sources)
        $primaireSrc = $documentsSources[0];

        return Ecart::create([
            'analyse_id' => $analyse->id,
            'referentiel_id' => $exigence->referentiel_id,
            'referentiel_chunk_id' => $exigence->id,
            'document_id' => $primaireSrc['document_id'],
            'gravite' => $gravitePrimaire,
            'categorie' => $exigence->categorie_exigence ?? 'autre',
            'type_ecart' => $typeAgrege,
            'titre' => $titre,
            'exigence_referentiel' => mb_substr($exigence->contenu, 0, 2000),
            'article_reference' => $exigence->article_reference,
            'description_ecart' => $description,
            'risque' => $risque,
            'recommandation' => $recommandation,
            'extrait_document' => $primaireSrc['extrait_document'],
            'documents_sources' => $documentsSources,
            'source_fichier' => $primaireSrc['titre'] ?? $primaireSrc['nom_fichier'],
            'question_numero' => $primaireSrc['question_numero'],
            'question_texte' => $primaireSrc['question_texte'],
            'reponse_client' => $primaireSrc['reponse_client'],
            'score_similarite' => $primaireSrc['score_similarite'],
            'statut_correction' => 'ouvert',
        ]);
    }

    /**
     * Verifie qu'Ollama est joignable ET que le modele de chat repond effectivement
     * a un appel test (warm-up). Sert a charger le modele en RAM avant la rafale
     * d'appels et a basculer immediatement sur templates si Ollama n'est pas pret.
     */
    private function verifierLlmReady(int $analyseId): bool
    {
        if (! $this->llm->estDisponible()) {
            Log::info("Analyse {$analyseId} : Ollama injoignable, redaction via templates.");

            return false;
        }

        try {
            $t0 = microtime(true);
            $rep = $this->llm->completer(
                messages: [['role' => 'user', 'content' => 'Reponds uniquement "OK".']],
                temperature: 0.0,
                maxTokens: 5,
            );
            $duree = round((microtime(true) - $t0) * 1000);
            $contenu = trim($rep['content'] ?? '');
            if ($contenu === '') {
                Log::warning("Analyse {$analyseId} : warm-up LLM a renvoye une reponse vide, bascule templates.");

                return false;
            }
            Log::info("Analyse {$analyseId} : LLM Ollama pret (warm-up en {$duree}ms), redaction via IA.");

            return true;
        } catch (\Throwable $e) {
            Log::warning("Analyse {$analyseId} : warm-up LLM echoue, bascule templates.", ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Version de redigerEcartAvecLlm qui retourne aussi un flag indiquant si le LLM
     * a vraiment repondu (true) ou si on a du fallbacker (false). Permet au circuit
     * breaker de detecter une panne Ollama et basculer sur templates.
     *
     * @return array{0:string,1:string,2:string,3:string,4:bool} [description, risque, recommandation, titre, llmOk]
     */
    private function redigerEcartAvecLlmSafe(ReferentielChunk $exigence, array $verdict): array
    {
        $typeEcart = $verdict['type_ecart'];
        $extraitDoc = $verdict['extrait_document'] ?? '(aucun document pertinent trouve cote client)';
        $articleRef = $exigence->article_reference ?: 'cette exigence';

        $prompt = $this->construirePromptRedaction($exigence->contenu, $extraitDoc, $articleRef, $typeEcart);

        try {
            $resultat = $this->llm->completer(
                messages: [['role' => 'user', 'content' => $prompt]],
                temperature: 0.2,
                maxTokens: 600,
            );

            $parsed = $this->parserReponseLlm($resultat['content'] ?? '');
            if ($parsed !== null) {
                return [...$parsed, true];
            }
        } catch (\Throwable $e) {
            Log::warning('LLM indisponible pour redaction ecart — fallback texte', ['error' => $e->getMessage()]);
        }

        // LLM a echoue ou reponse non parsable : fallback template
        return [...$this->redactionFallback($exigence, $typeEcart), false];
    }

    private function redigerEcartAvecLlm(ReferentielChunk $exigence, array $verdict): array
    {
        $typeEcart = $verdict['type_ecart'];
        $extraitDoc = $verdict['extrait_document'] ?? '(aucun document pertinent trouve cote client)';
        $articleRef = $exigence->article_reference ?: 'cette exigence';

        $prompt = $this->construirePromptRedaction($exigence->contenu, $extraitDoc, $articleRef, $typeEcart);

        try {
            $resultat = $this->llm->completer(
                messages: [['role' => 'user', 'content' => $prompt]],
                temperature: 0.2,
                maxTokens: 600,
            );

            $parsed = $this->parserReponseLlm($resultat['content'] ?? '');
            if ($parsed !== null) {
                return $parsed;
            }
        } catch (\Throwable $e) {
            Log::warning('LLM indisponible pour redaction ecart — fallback texte', ['error' => $e->getMessage()]);
        }

        return $this->redactionFallback($exigence, $typeEcart);
    }

    /**
     * Construit un prompt qui force l'IA a etre concrete :
     *   - identifier ce qui MANQUE dans le doc client par rapport a l'exigence
     *   - decrire le RISQUE specifique (pas une formule passe-partout)
     *   - donner une action OPERATIONNELLE
     * Few-shot example inclus pour caler le ton et le niveau de detail.
     */
    private function construirePromptRedaction(string $exigenceTexte, string $extraitClient, string $articleRef, string $typeEcart): string
    {
        return <<<PROMPT
Tu es consultant DCP en Côte d'Ivoire (ARTCI, loi 2013-450). Tu rédiges un constat d'écart **concret et actionnable, en français correctement accentué**.

RÈGLE ABSOLUE : pas de phrase passe-partout du genre "fournir ou mettre à jour la documentation". Tu dois :
1. Identifier PRÉCISÉMENT ce qui manque ou est insuffisant dans le document client (citer le concept manquant : durée, registre, consentement, DPO, mesure technique, etc.)
2. Décrire le RISQUE OPÉRATIONNEL ou JURIDIQUE spécifique encouru (sanction ARTCI jusqu'à 100 MFCFA, conservation excessive, fuite, non-respect droits des personnes, etc.)
3. Donner une RECOMMANDATION qui dit QUOI mettre en place, AVEC QUEL CONTENU, et idéalement le format attendu (procédure écrite, clause contractuelle, registre, ...).

EXEMPLE de niveau de détail attendu :
Exigence : "L'organisation doit définir une durée de conservation des données."
Doc client : "Les données clients sont stockées dans nos bases."
=> {"titre":"Absence de politique de conservation des données","description":"Le document client mentionne le stockage mais ne fixe ni durée de conservation, ni politique d'archivage, ni procédure de suppression. L'exigence sur la durée de conservation n'est donc pas couverte.","risque":"Conservation excessive et indue des données personnelles : non-respect du principe de limitation de conservation (article 35 loi 2013-450), exposition à sanction ARTCI et perte de confiance des personnes concernées.","recommandation":"Rédiger une politique de conservation indiquant pour chaque catégorie de données : (1) la durée de conservation active, (2) la durée d'archivage intermédiaire avec base légale, (3) la procédure de suppression/anonymisation automatique en fin de cycle. Documenter ces durées dans le registre des traitements."}

CONTEXTE DE TON ANALYSE :
EXIGENCE ({$articleRef}) :
{$exigenceTexte}

TYPE D'ÉCART DÉTECTÉ : {$typeEcart}

EXTRAIT DU DOCUMENT CLIENT :
{$extraitClient}

Réponds UNIQUEMENT en JSON valide sur une seule ligne, sans markdown, sans commentaire. Tout le texte doit être en français correctement accentué (é, è, ê, à, ç, etc.) :
{"titre":"15 mots max, sans 'Écart -'","description":"2-4 phrases concrètes citant ce qui manque","risque":"1-3 phrases sur le risque spécifique (juridique + opérationnel)","recommandation":"action opérationnelle précise, 2-4 phrases, avec contenu attendu"}
PROMPT;
    }

    /**
     * Parse la reponse JSON du LLM avec tolerance aux variations courantes :
     *   - Bloc markdown ```json ... ``` (que llama3.2 ajoute parfois)
     *   - Texte parasite avant/apres l'objet JSON
     *   - Champs alternatifs (description/constat, recommandation/reco)
     * Retourne [description, risque, recommandation, titre] ou null si rien d'utilisable.
     */
    private function parserReponseLlm(string $contenu): ?array
    {
        // Nettoyer les blocs markdown
        $contenu = preg_replace('/```(?:json)?\s*/i', '', $contenu);
        $contenu = str_replace('```', '', $contenu);

        // Extraire le premier objet JSON valide en essayant plusieurs decoupages
        $candidat = null;
        if (preg_match('/\{[\s\S]*\}/', $contenu, $m)) {
            $candidat = $m[0];
        }
        if ($candidat === null) {
            return null;
        }

        $json = json_decode($candidat, true);

        // Tentative de reparation si JSON cassé (virgule finale, guillemets simples...)
        if ($json === null) {
            $repare = preg_replace('/,\s*}/', '}', $candidat);
            $repare = preg_replace('/,\s*\]/', ']', $repare);
            $json = json_decode($repare, true);
        }

        if (! is_array($json)) {
            return null;
        }

        // Accepter plusieurs noms de champs
        $titre = $json['titre'] ?? $json['title'] ?? null;
        $description = $json['description'] ?? $json['constat'] ?? $json['ecart'] ?? null;
        $risque = $json['risque'] ?? $json['risk'] ?? '';
        $recommandation = $json['recommandation'] ?? $json['reco'] ?? $json['recommendation'] ?? '';

        if ($titre === null && $description === null) {
            return null;
        }

        return [
            trim($description ?? 'Ecart detecte.'),
            trim($risque),
            trim($recommandation),
            mb_substr(trim($titre ?? 'Ecart detecte'), 0, 255),
        ];
    }

    /**
     * Fallback sans LLM : produit constat + risque + reco specifiques au theme DCP
     * detecte dans l'exigence (conservation, consentement, DPO, transfert, etc.).
     * Plus utile que le texte passe-partout precedent.
     *
     * @return array{0:string,1:string,2:string,3:string} [description, risque, recommandation, titre]
     */
    private function redactionFallback(ReferentielChunk $exigence, string $typeEcart): array
    {
        // Priorite a theme_dcp (calcule UNE FOIS par le LLM a l'indexation et
        // persiste en DB). On valide le theme contre le catalogue : si la
        // colonne contient un theme inconnu (vieille donnee, faute LLM), on
        // bascule sur l'heuristique de secours.
        $catalogue = $this->templatesParTheme();
        $theme = $exigence->theme_dcp ?? null;
        if (! $theme || ! isset($catalogue[$theme])) {
            $theme = $this->detecterThemeDcp($exigence->contenu);
        }

        $ref = $exigence->article_reference ?? 'l\'exigence';

        $template = $catalogue[$theme] ?? $catalogue['autre'];
        $intro = $typeEcart === 'absence_totale'
            ? 'Aucune preuve documentaire n\'a été trouvée'
            : 'La preuve documentaire fournie est insuffisante';

        $description = sprintf(
            '%s pour couvrir l\'exigence portant sur %s. %s',
            $intro,
            $template['concept'],
            $template['constat']
        );

        $titre = mb_substr($template['titre'] . ' (' . $ref . ')', 0, 255);

        return [$description, $template['risque'], $template['recommandation'], $titre];
    }

    /**
     * Catalogue de fallbacks par theme DCP. Chaque entree fournit un constat,
     * un risque et une recommandation operationnels.
     */
    private function templatesParTheme(): array
    {
        return [
            'donnees_sensibles' => [
                'titre' => 'Traitement de données sensibles sans encadrement légal',
                'concept' => 'le traitement de données sensibles (santé, biométrie, casier judiciaire, origine, opinions, vie sexuelle)',
                'constat' => 'Le document client ne démontre pas que le traitement des données sensibles fait l\'objet d\'une autorisation préalable de l\'ARTCI, d\'une AIPD, ni de mesures de sécurité renforcées adaptées à la nature de ces données.',
                'risque' => 'Article 21 de la loi 2013-450 : le traitement non autorisé de données sensibles est puni d\'une peine d\'emprisonnement de 10 à 20 ans et de 20 à 40 millions FCFA d\'amende. Sanction administrative ARTCI en sus.',
                'recommandation' => 'Identifier l\'ensemble des traitements portant sur des données sensibles (santé, casier, biométrie, etc.). Pour chacun : (1) déposer une demande d\'autorisation préalable à l\'ARTCI avec dossier complet, (2) réaliser une AIPD, (3) mettre en place des mesures de sécurité renforcées (chiffrement, restriction d\'accès, journalisation), (4) documenter la base légale stricte (consentement explicite, sauvegarde des intérêts vitaux, obligation légale, etc.).',
            ],
            'conservation' => [
                'titre' => 'Politique de conservation des données absente ou incomplète',
                'concept' => 'la durée et la politique de conservation des données',
                'constat' => 'Aucune durée de conservation, ni politique d\'archivage, ni procédure de suppression/anonymisation n\'est documentée pour les catégories de données concernées.',
                'risque' => 'Conservation excessive et indue des données personnelles, en violation du principe de limitation de conservation (article 35 de la loi 2013-450). Risque de sanction ARTCI et atteinte aux droits des personnes concernées (droit à l\'effacement).',
                'recommandation' => 'Rédiger une politique de conservation indiquant pour chaque catégorie de données : (1) durée de conservation active, (2) durée d\'archivage intermédiaire avec base légale, (3) procédure automatique de suppression/anonymisation en fin de cycle. Reporter ces durées dans le registre des traitements.',
            ],
            'consentement' => [
                'titre' => 'Recueil du consentement non conforme',
                'concept' => 'le recueil du consentement libre, spécifique, éclairé et univoque',
                'constat' => 'Le document client ne démontre pas comment le consentement est recueilli (mention d\'information préalable, granularité par finalité, mécanisme de retrait, traçabilité).',
                'risque' => 'Traitement effectué sans base légale valide (article 14 loi 2013-450). Risque d\'action des personnes concernées, sanction ARTCI jusqu\'à 100 MFCFA, et invalidation des opérations réalisées sur ces données.',
                'recommandation' => 'Mettre en place un mécanisme de consentement granulaire (case à cocher non pré-cochée par finalité), accompagné d\'une mention d\'information complète (responsable, finalités, destinataires, durées, droits, ARTCI), et conserver une preuve horodatée du consentement avec procédure de retrait simple et symétrique.',
            ],
            'dpo' => [
                'titre' => 'Correspondant à la protection des données non désigné',
                'concept' => 'la désignation et le rôle du Correspondant à la Protection des Données (CPD/DPO)',
                'constat' => 'Aucune désignation formelle d\'un CPD/DPO n\'est documentée, ni sa lettre de mission, ses moyens, ni la procédure de saisine par les personnes concernées.',
                'risque' => 'Défaut de gouvernance DCP : absence de point de contact pour l\'ARTCI et les personnes, traitement des réclamations non maîtrisé, sanction administrative possible.',
                'recommandation' => 'Désigner formellement un CPD via une lettre de mission signée par la direction, déclarer sa nomination à l\'ARTCI, publier ses coordonnées (mail dédié) sur le site et dans les mentions d\'information, et lui garantir l\'indépendance + les moyens (formation, accès à la direction).',
            ],
            'transfert' => [
                'titre' => 'Transfert de données hors zone non encadré',
                'concept' => 'l\'encadrement des transferts de données hors Côte d\'Ivoire',
                'constat' => 'Aucune autorisation préalable de l\'ARTCI, ni clauses contractuelles types, ni évaluation du niveau de protection du pays destinataire ne sont produites pour les flux sortants.',
                'risque' => 'Transfert illicite : suspension possible des flux par l\'ARTCI, exposition des données à une législation moins protectrice, sanction pénale en cas de fuite à l\'étranger.',
                'recommandation' => 'Cartographier les transferts hors CI (sous-traitants, cloud, maison-mère), déposer une demande d\'autorisation ARTCI pour chacun, intégrer des clauses contractuelles types (responsabilité, sécurité, droits des personnes) et évaluer le niveau de protection du pays destinataire.',
            ],
            'securite' => [
                'titre' => 'Mesures de sécurité insuffisantes ou non documentées',
                'concept' => 'les mesures techniques et organisationnelles de sécurité (article 39-40)',
                'constat' => 'Les mesures de sécurité (chiffrement, pseudonymisation, gestion des accès, sauvegardes, journalisation) ne sont ni décrites ni associées à une évaluation de risque pour les données personnelles traitées.',
                'risque' => 'Risque élevé de violation de données (fuite, altération, destruction), obligation de notification ARTCI sous 72h non maîtrisée, mise en cause pénale du responsable de traitement.',
                'recommandation' => 'Élaborer une PSSI intégrant un volet DCP : matrice des accès par profil, chiffrement au repos et en transit, journalisation des accès aux données sensibles, sauvegardes chiffrées testées, procédure de gestion des incidents avec notification ARTCI sous 72h.',
            ],
            'registre' => [
                'titre' => 'Registre des traitements absent ou incomplet',
                'concept' => 'la tenue du registre des activités de traitement',
                'constat' => 'Aucun registre des traitements n\'est produit, ou les entrées existantes ne contiennent pas l\'ensemble des champs requis (finalité, base légale, catégories de données, destinataires, durées, mesures de sécurité).',
                'risque' => 'Impossibilité de démontrer la conformité en cas de contrôle ARTCI. Mauvaise maîtrise des flux internes. Risque de sanction administrative et d\'aggravation en cas d\'incident.',
                'recommandation' => 'Mettre en place un registre des traitements (Excel ou outil dédié) renseignant pour chaque traitement : responsable, finalité, base légale, catégories de personnes/données, destinataires/sous-traitants, transferts, durées de conservation, mesures techniques et organisationnelles. Le faire revoir trimestriellement par le CPD.',
            ],
            'droits' => [
                'titre' => 'Exercice des droits des personnes concernées non garanti',
                'concept' => 'le droit d\'accès, de rectification, d\'opposition et de suppression',
                'constat' => 'Aucune procédure d\'instruction des demandes d\'exercice des droits n\'est documentée : canal de réception, délai légal (30 jours), modèle de réponse, registre des demandes.',
                'risque' => 'Non-respect des droits des personnes (article 41-50 loi 2013-450) : réclamation auprès de l\'ARTCI, atteinte à l\'image, sanction administrative.',
                'recommandation' => 'Publier un canal dédié (formulaire web + email), formaliser une procédure interne avec vérification d\'identité, qualification de la demande, instruction sous 30 jours, traitement des refus motivés, et journalisation dans un registre des demandes.',
            ],
            'sous_traitance' => [
                'titre' => 'Contrats de sous-traitance non conformes',
                'concept' => 'l\'encadrement contractuel des sous-traitants traitant des données personnelles',
                'constat' => 'Les contrats avec les sous-traitants ne comportent pas les clauses obligatoires : objet/durée du traitement, instructions documentées, confidentialité, sécurité, sous-traitance ultérieure, restitution/suppression, audit.',
                'risque' => 'Responsabilité solidaire du responsable de traitement en cas de manquement du sous-traitant. Risque de fuite via prestataire, sanction ARTCI, et impossibilité d\'exiger les mesures correctives.',
                'recommandation' => 'Ajouter aux contrats actifs un avenant DCP intégrant : nature/finalité/durée, instructions écrites, obligation de sécurité proportionnée, interdiction de sous-traitance ultérieure sans autorisation, droit d\'audit, restitution/effacement des données en fin de contrat, notification d\'incident sous 48h.',
            ],
            'aipd' => [
                'titre' => 'Analyse d\'impact (AIPD) non réalisée',
                'concept' => 'l\'analyse d\'impact sur la vie privée pour traitements à risque élevé',
                'constat' => 'Aucune AIPD n\'est documentée pour les traitements présentant un risque élevé (données sensibles, profilage, surveillance, traitements à grande échelle).',
                'risque' => 'Démarrage d\'un traitement à risque sans démonstration de proportionnalité : refus d\'autorisation ARTCI, suspension du traitement, sanction administrative.',
                'recommandation' => 'Réaliser une AIPD pour chaque traitement à risque, comprenant : description détaillée, nécessité/proportionnalité, évaluation des risques pour les personnes, mesures de mitigation, avis du CPD. Joindre l\'AIPD au dossier de déclaration ARTCI.',
            ],
            'information' => [
                'titre' => 'Information des personnes concernées insuffisante',
                'concept' => 'l\'obligation d\'information lors de la collecte des données',
                'constat' => 'Les mentions d\'information délivrées aux personnes ne comportent pas l\'ensemble des éléments requis (identité du responsable, finalités, base légale, destinataires, durées, droits, ARTCI).',
                'risque' => 'Collecte déloyale au sens de l\'article 30 de la loi 2013-450 : invalidité du consentement, action des personnes, sanction administrative.',
                'recommandation' => 'Refondre les mentions d\'information sur tous les formulaires (papier, web, app) en intégrant : qui est le responsable, pourquoi sont collectées les données, sur quelle base légale, qui les reçoit, combien de temps, quels droits, comment saisir l\'ARTCI. Faire valider par le CPD.',
            ],
            'notification_violation' => [
                'titre' => 'Procédure de notification de violation de données absente',
                'concept' => 'la notification des violations de données à caractère personnel',
                'constat' => 'Aucune procédure interne ne décrit comment détecter, qualifier et notifier à l\'ARTCI une violation de données dans les délais légaux (sous 72h), ni comment informer les personnes concernées en cas de risque élevé.',
                'risque' => 'Aggravation des sanctions ARTCI en cas d\'incident : amende renforcée pour défaut de notification, perte de confiance des personnes, atteinte à l\'image. Le délai de 72h se compte dès la connaissance du fait.',
                'recommandation' => 'Rédiger une procédure de gestion des violations comportant : (1) canal interne de signalement, (2) cellule de crise et rôles, (3) qualification du risque pour les personnes, (4) modèle de notification ARTCI (avec faits, catégories de données, mesures prises), (5) modèle d\'information aux personnes concernées en cas de risque élevé, (6) registre des violations.',
            ],
            'responsable_traitement' => [
                'titre' => 'Identification du responsable de traitement non documentée',
                'concept' => 'la désignation et la responsabilité du responsable du traitement',
                'constat' => 'Le document client ne formalise pas qui est le responsable de traitement pour chaque activité (entité juridique, dirigeant signataire), ni la répartition responsable/sous-traitant.',
                'risque' => 'Imputabilité floue en cas de contrôle ARTCI ou de plainte : pas de point de responsabilité identifiable, sanction pouvant viser plusieurs entités du groupe par défaut.',
                'recommandation' => 'Identifier dans le registre des traitements le responsable de traitement pour chaque activité (raison sociale, RCCM, dirigeant), et le formaliser dans les mentions d\'information délivrées aux personnes concernées. Distinguer clairement responsable, co-responsables et sous-traitants.',
            ],
            'formalites_artci' => [
                'titre' => 'Formalités préalables ARTCI non accomplies',
                'concept' => 'les formalités préalables auprès de l\'ARTCI (déclaration, autorisation, avis)',
                'constat' => 'Le document client ne démontre pas que les traitements concernés ont fait l\'objet d\'une déclaration normale, simplifiée ou autorisation préalable de l\'ARTCI selon leur régime applicable.',
                'risque' => 'Traitement effectué sans titre régulier : nullité des opérations, sanction administrative ARTCI, et en cas de données sensibles ou de transferts, sanctions pénales prévues à la loi 2013-450.',
                'recommandation' => 'Cartographier les traitements par régime (déclaration normale, simplifiée, autorisation préalable), constituer un dossier ARTCI pour chacun et conserver les récépissés. Désigner une personne responsable du suivi des formalités.',
            ],
            'principes' => [
                'titre' => 'Principes fondamentaux du traitement non respectés ou non documentés',
                'concept' => 'les principes de licité, loyauté, transparence, finalité déterminée, minimisation, exactitude',
                'constat' => 'Le document client ne démontre pas le respect des principes fondamentaux : finalité explicite et légitime, données adéquates et limitées à ce qui est nécessaire, exactitude et mise à jour, base légale claire.',
                'risque' => 'Traitement potentiellement illicite ou disproportionné : invalidation des données, action des personnes concernées, sanction ARTCI.',
                'recommandation' => 'Pour chaque traitement, documenter explicitement : finalité (ce qu\'on cherche à faire), base légale (consentement, contrat, obligation légale, intérêt légitime), catégories de données strictement nécessaires, mécanismes de mise à jour. Auditer la minimisation au moins annuellement.',
            ],
            'mineurs' => [
                'titre' => 'Traitement des données de mineurs sans cadre spécifique',
                'concept' => 'le traitement des données à caractère personnel des mineurs',
                'constat' => 'Le document client ne prévoit pas de cadre spécifique pour les données concernant des mineurs : consentement de l\'autorité parentale, mention d\'information adaptée, restrictions de traitement.',
                'risque' => 'Traitement contestable des données de mineurs : sanction renforcée, action des représentants légaux, atteinte à l\'image de l\'organisation.',
                'recommandation' => 'Identifier les traitements pouvant concerner des mineurs. Mettre en place : (1) recueil du consentement parental, (2) mention d\'information adaptée à l\'âge, (3) restrictions par défaut (pas de prospection, profilage limité), (4) procédure d\'exercice des droits par les parents.',
            ],
            'marketing' => [
                'titre' => 'Prospection / marketing direct sans encadrement DCP',
                'concept' => 'la prospection commerciale et le marketing direct',
                'constat' => 'Les opérations de prospection (e-mail, SMS, téléphone) ne sont pas encadrées par un mécanisme de consentement granulaire, de droit d\'opposition simple et de durée limitée de conservation des données.',
                'risque' => 'Prospection illicite : action des personnes concernées, plainte ARTCI, sanction administrative, atteinte à la réputation.',
                'recommandation' => 'Mettre en place : (1) consentement opt-in explicite pour la prospection électronique, (2) lien de désinscription dans chaque envoi, (3) registre des oppositions à jour, (4) durée de conservation maximale 3 ans après dernier contact, (5) information claire dans les mentions légales.',
            ],
            'portabilite' => [
                'titre' => 'Droit à la portabilité des données non opérationnel',
                'concept' => 'le droit pour la personne concernée d\'obtenir ses données dans un format structuré et de les transmettre',
                'constat' => 'Aucune procédure ni outil ne permet à la personne concernée d\'obtenir une copie de ses données dans un format structuré, couramment utilisé et lisible par machine, ni de les faire transférer à un autre responsable de traitement.',
                'risque' => 'Non-respect du droit à la portabilité (article 38 loi 2013-450) : réclamation auprès de l\'ARTCI, atteinte à la confiance des personnes concernées, sanction administrative.',
                'recommandation' => 'Mettre en place une procédure d\'extraction des données personnelles dans un format ouvert (CSV, JSON, XML), un canal dédié pour recevoir les demandes, et une instruction sous 30 jours avec vérification d\'identité. Pour les transferts vers un autre responsable de traitement, prévoir un protocole technique (export sécurisé) et une journalisation dans le registre des demandes.',
            ],
            'autre' => [
                'titre' => 'Exigence non couverte par la documentation client',
                'concept' => 'l\'exigence présentée dans l\'extrait du référentiel',
                'constat' => 'Le document client analysé ne contient pas d\'élément démontrant la prise en compte de cette exigence.',
                'risque' => 'Non-conformité à la loi 2013-450 / aux orientations ARTCI : exposition à un contrôle, sanction administrative, et éventuelles actions des personnes concernées.',
                'recommandation' => 'Identifier le processus interne concerné, documenter formellement la mesure attendue (procédure écrite, clause, mention, registre selon le cas), faire valider par le CPD et joindre la preuve au dossier de conformité.',
            ],
        ];
    }

    /**
     * Detecte le theme DCP dominant d'une exigence.
     *
     * Strategie : compter les occurrences ponderees de chaque famille de mots-cles
     * et retourner le theme avec le score le plus eleve. Cela evite que "consentement"
     * gagne sur une exigence qui parle d'abord et longuement de donnees sensibles
     * mais qui finit par "...sans le consentement des personnes concernees".
     */
    private function detecterThemeDcp(string $texteExigence): string
    {
        // Minuscules + strip accents : sans cela, "correspondant a" du catalogue
        // ne matche pas "correspondant à" du texte (les chunks ARTCI contiennent
        // les accents francais). On utilise un strtr manuel car iconv TRANSLIT
        // sur Windows produit `a / 'e (caracteres parasites) au lieu de a / e.
        $texte = strtr(mb_strtolower($texteExigence), [
            'à'=>'a','â'=>'a','ä'=>'a','á'=>'a',
            'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'î'=>'i','ï'=>'i','í'=>'i',
            'ô'=>'o','ö'=>'o','ó'=>'o','õ'=>'o',
            'ù'=>'u','û'=>'u','ü'=>'u','ú'=>'u',
            'ç'=>'c','ñ'=>'n','ÿ'=>'y',
        ]);
        // L'ordre n'a plus d'importance — c'est le poids qui tranche.
        // Catalogue elargi pour reduire la part d'exigences classees en "autre" :
        // chaque famille a ete enrichie avec des synonymes et expressions courantes
        // de la loi 2013-450 et des delibérations ARTCI.
        $regles = [
            'donnees_sensibles' => ['donnees sensibles', 'categorie particuliere', 'donnees genetiques', 'donnees biometriques', 'biometrie', 'origine raciale', 'origine ethnique', 'opinions politiques', 'convictions religieuses', 'philosophiques', 'appartenance syndicale', 'vie sexuelle', 'orientation sexuelle', 'etat de sante', 'casier judiciaire', 'donnees de sante', 'donnees medicales', 'condamnation', 'infraction', 'sanction disciplinaire'],
            'conservation' => ['conservation', 'duree de conservation', 'archivage', 'archives', 'suppression', 'effacement', 'limitation de la duree', 'duree limitee', 'destruction des donnees', 'purge des donnees', 'retention'],
            'consentement' => ['consentement', 'libre, specifique, eclaire', 'recueil du consentement', 'opt-in', 'opt in', 'retrait du consentement', 'consentement prealable', 'consentement explicite', 'consentement de la personne'],
            'dpo' => ['delegue a la protection', 'correspondant a la protection', 'correspondant a la protection des donnees', 'cpd', 'dpo', 'referent dcp', 'referent donnees personnelles', 'point de contact dcp'],
            'transfert' => ['transfert hors', 'transfert international', 'pays tiers', 'flux transfrontalier', 'transfert de donnees', 'maison-mere', 'maison mere', 'hors de la cote d\'ivoire', 'hors ci', 'pays etranger', 'autorisation de transfert', 'flux sortant'],
            'securite' => ['mesure de securite', 'mesures de securite', 'mesures techniques', 'mesures organisationnelles', 'chiffrement', 'cryptographie', 'pseudonymisation', 'anonymisation', 'integrite', 'confidentialite', 'disponibilite', 'sauvegarde', 'journalisation', 'tracabilite', 'controle d\'acces', 'authentification', 'mot de passe', 'habilitation', 'restauration'],
            'registre' => ['registre des traitements', 'registre des activites', 'cartographie des traitements', 'inventaire des traitements', 'documentation des traitements', 'registre', 'tenue d\'un registre'],
            'droits' => ['droit d\'acces', 'droit a l\'effacement', 'droit a l\'oubli', 'droit de rectification', 'droit d\'opposition', 'exercice des droits', 'droit a la portabilite', 'droit a la limitation', 'reponse aux demandes', 'demande de la personne concernee'],
            'sous_traitance' => ['sous-traitant', 'sous traitant', 'sous-traitance', 'donneur d\'ordre', 'contrat de sous-traitance', 'prestataire de service', 'fournisseur de service', 'clause contractuelle'],
            'aipd' => ['aipd', 'analyse d\'impact', 'evaluation d\'impact', 'pia', 'evaluation des risques sur la vie privee', 'etude d\'impact', 'analyse prealable des risques'],
            'information' => ['mention d\'information', 'information des personnes', 'information prealable', 'information de la personne concernee', 'information du public', 'transparence', 'mention legale', 'finalite du traitement', 'identite du responsable'],
            'notification_violation' => ['notification de violation', 'notification a l\'autorite', 'violation de donnees', 'fuite de donnees', 'incident de securite', 'breach', '72 heures'],
            // Les termes "responsable du traitement" / "responsable de traitement"
            // apparaissent dans presque CHAQUE article de la loi 2013-450 (acteur
            // central mentionne partout). Les inclure ici ferait gagner ce theme
            // sur a peu pres toutes les exigences, eclipsant le vrai sujet (securite,
            // DPO, sanctions, etc.). On ne retient que les termes specifiques a
            // l'identification/designation, qui sont eux rares dans le texte.
            'responsable_traitement' => ['co-responsable', 'identification du responsable', 'designation du responsable', 'identite du responsable'],
            'formalites_artci' => ['declaration prealable', 'autorisation prealable', 'formalite prealable', 'demande aupres de l\'artci', 'depot d\'un dossier', 'artci', 'autorite de protection'],
            'principes' => ['licite, loyale et transparente', 'licite et loyale', 'finalite determinee', 'finalite explicite', 'minimisation des donnees', 'donnees adequates', 'donnees pertinentes', 'donnees exactes', 'donnees a jour'],
            'mineurs' => ['mineur', 'mineurs', 'enfant', 'autorite parentale', 'representant legal'],
            'marketing' => ['prospection', 'marketing direct', 'demarchage', 'newsletter', 'publicite ciblee', 'profilage publicitaire'],
        ];

        $scores = [];
        foreach ($regles as $theme => $motsCles) {
            $score = 0;
            foreach ($motsCles as $mot) {
                // mb_substr_count compte les occurrences (plus parlant que str_contains)
                $score += substr_count($texte, $mot);
            }
            if ($score > 0) {
                $scores[$theme] = $score;
            }
        }

        if (empty($scores)) {
            return 'autre';
        }

        arsort($scores);

        return array_key_first($scores);
    }

    private function determinerGravite(ReferentielChunk $exigence, ?string $typeEcart): string
    {
        $texte = mb_strtolower($exigence->contenu);
        $critiques = ['consentement', 'donnees sensibles', 'aipd obligatoire', 'notification violation', 'transfert international', 'mineur'];
        foreach ($critiques as $mot) {
            if (str_contains($texte, $mot)) {
                return 'critique';
            }
        }

        return match ($typeEcart) {
            'non_conformite' => 'majeur',
            'preuve_insuffisante' => 'mineur',
            default => 'majeur',
        };
    }

    private function extraireMotsCles(string $texte): array
    {
        $motsVides = ['le', 'la', 'les', 'un', 'une', 'des', 'de', 'du', 'et', 'ou', 'a', 'au', 'aux', 'en', 'par', 'pour', 'dans', 'sur', 'est', 'sont', 'que', 'qui', 'ce', 'cette', 'avec', 'sans', 'leur', 'leurs', 'son', 'sa', 'ses'];
        $mots = preg_split('/[\s\p{P}]+/u', mb_strtolower($texte));
        $mots = array_filter($mots, fn ($m) => mb_strlen($m) > 3 && ! in_array($m, $motsVides, true));

        return array_slice(array_unique(array_values($mots)), 0, 8);
    }

    /**
     * Score de conformite metier : % d'exigences couvertes par au moins un document,
     * pondere par la gravite de chaque exigence (critique x3, majeure x2, mineure x1).
     *
     * Un score de 60% signifie "60% des exigences DCP sont couvertes au moins
     * partiellement par votre documentation, pondere par leur importance".
     */
    private function calculerScoreConformite($exigences, array $conformesParExigence): float
    {
        $poidsTotal = 0;
        $poidsCouverts = 0;

        foreach ($exigences as $exigence) {
            $gravite = $this->determinerGravite($exigence, null);
            $poids = match ($gravite) {
                'critique' => 3,
                'majeur' => 2,
                default => 1,
            };
            $poidsTotal += $poids;
            if (isset($conformesParExigence[$exigence->id])) {
                $poidsCouverts += $poids;
            }
        }

        if ($poidsTotal === 0) {
            return 0.0;
        }

        return round(($poidsCouverts / $poidsTotal) * 100, 2);
    }

    private function construireSynthese(Analyse $analyse, array $conformiteParDoc = []): array
    {
        $ecarts = $analyse->ecarts()->get();

        return [
            'total_ecarts' => $ecarts->count(),
            'par_gravite' => [
                'critique' => $ecarts->where('gravite', 'critique')->count(),
                'majeur' => $ecarts->where('gravite', 'majeur')->count(),
                'mineur' => $ecarts->where('gravite', 'mineur')->count(),
                'observation' => $ecarts->where('gravite', 'observation')->count(),
            ],
            'par_categorie' => $ecarts->groupBy('categorie')->map->count(),
            'par_type' => $ecarts->groupBy('type_ecart')->map->count(),
            'score_conformite' => $analyse->score_conformite,
            'conformite_documents' => $conformiteParDoc,
            'nb_documents_conformes' => collect($conformiteParDoc)->where('statut', 'conforme')->count(),
            'nb_documents_non_conformes' => collect($conformiteParDoc)->where('statut', 'non_conforme')->count(),
            'nb_documents_non_evalues' => collect($conformiteParDoc)->where('statut', 'non_evalue')->count(),
        ];
    }

    private function genererCommentaireSynthese(Analyse $analyse): ?string
    {
        // En mode rapide : synthese automatique textuelle (pas d'appel LLM)
        if (! $analyse->enrichissement_ia) {
            return sprintf(
                'Analyse automatique terminée : %d exigences vérifiées, score de conformité %s%%. %d écarts critiques, %d majeurs, %d mineurs ont été détectés. Un examen consultant est recommandé pour enrichir et valider les constats. L\'enrichissement IA peut être lancé ultérieurement via le bouton dédié.',
                $analyse->nb_exigences_verifiees,
                $analyse->score_conformite,
                $analyse->nb_ecarts_critiques,
                $analyse->nb_ecarts_majeurs,
                $analyse->nb_ecarts_mineurs
            );
        }

        try {
            $prompt = <<<PROMPT
Rédige une synthèse exécutive (5-8 phrases) d'un rapport d'écarts ARTCI, en français correctement accentué.
DONNÉES :
- Exigences vérifiées : {$analyse->nb_exigences_verifiees}
- Critiques : {$analyse->nb_ecarts_critiques}, Majeurs : {$analyse->nb_ecarts_majeurs}, Mineurs : {$analyse->nb_ecarts_mineurs}
- Score : {$analyse->score_conformite}%
Structure : niveau de conformité global, points de vigilance, recommandation prioritaire. Ton factuel.
PROMPT;

            $resultat = $this->llm->completer(
                messages: [['role' => 'user', 'content' => $prompt]],
                temperature: 0.3,
                maxTokens: 400,
            );

            return trim($resultat['content'] ?? '');
        } catch (\Throwable $e) {
            return sprintf(
                'Analyse automatique terminée : %d exigences vérifiées, score %s%%. %d écarts critiques, %d majeurs, %d mineurs. Un examen consultant est recommandé pour valider les constats.',
                $analyse->nb_exigences_verifiees,
                $analyse->score_conformite,
                $analyse->nb_ecarts_critiques,
                $analyse->nb_ecarts_majeurs,
                $analyse->nb_ecarts_mineurs
            );
        }
    }
}
