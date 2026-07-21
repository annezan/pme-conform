<?php

/**
 * Job IndexReferentielJob — Indexation asynchrone d'un referentiel.
 *
 * Pipeline : extraction texte (si non fait) -> decoupe par article/exigence
 *            -> embeddings -> stockage dans referentiel_chunks.
 *
 * La decoupe tente de detecter les articles ("Article X", "Art. X.Y")
 * pour conserver la granularite des exigences.
 */

namespace App\Jobs;

use App\Contracts\LLMConnectorInterface;
use App\Models\Referentiel;
use App\Models\ReferentielChunk;
use App\Services\Analyse\ThemeClassifierService;
use App\Services\Document\ExtractorFactory;
use App\Services\RAG\PgvectorChecker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class IndexReferentielJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 900;

    public function __construct(
        private Referentiel $referentiel,
    ) {
        $this->onQueue('referentiels');
    }

    public function handle(
        LLMConnectorInterface $llm,
        ExtractorFactory $extractorFactory,
        PgvectorChecker $pgvectorChecker,
        ThemeClassifierService $themeClassifier,
    ): void {
        // Job execute aussi en `dispatchAfterResponse` (mode sans worker).
        @set_time_limit(0);
        @ignore_user_abort(true);

        try {
            // Purger les chunks existants pour re-indexation
            $this->referentiel->chunks()->delete();

            $contenu = $this->referentiel->contenu_extrait;
            if (empty($contenu)) {
                $media = $this->referentiel->getFirstMedia('fichiers');
                if ($media && $extractorFactory->supporte($media->mime_type)) {
                    $contenu = $extractorFactory->extraire($media->getPath(), $media->mime_type);
                    $this->referentiel->update(['contenu_extrait' => $contenu]);
                }
            }

            if (empty($contenu)) {
                Log::warning("Referentiel {$this->referentiel->id} : aucun contenu a indexer.");
                return;
            }

            $chunks = $this->decouperParArticle($contenu);
            $pgvectorDisponible = $pgvectorChecker->estDisponible();

            // Si pgvector est dispo, on tente les embeddings — sauf si
            // explicitement desactive via OLLAMA_EMBEDDINGS_ENABLED=false (utile
            // en dev quand le seul modele Ollama est un chat lourd type llama3.2 :
            // chaque embedding prend 5-10s, ce qui rend l'indexation tres longue
            // sur des PDF de plusieurs dizaines de chunks). Le RAG bascule alors
            // automatiquement en fulltext, qui reste pleinement fonctionnel.
            $embeddingsActives = false;
            if ($pgvectorDisponible && config('services.ollama.embeddings_enabled', true)) {
                if ($llm->estDisponible()) {
                    $embeddingsActives = true;
                } else {
                    Log::warning("Referentiel {$this->referentiel->id} : Ollama indisponible — indexation sans embeddings (fallback fulltext).");
                }
            } elseif ($pgvectorDisponible) {
                Log::info("Referentiel {$this->referentiel->id} : embeddings desactives (OLLAMA_EMBEDDINGS_ENABLED=false) — indexation fulltext seule.");
            }

            // Classification thematique LLM : one-shot par chunk, persistee
            // sur referentiel_chunks.theme_dcp. Reutilisee gratuitement par
            // toutes les analyses futures (cf. GapAnalysisService::redactionFallback).
            // Si Ollama est indisponible, on laisse null et GapAnalysisService
            // bascule sur l'heuristique de secours.
            $themesActifs = $llm->estDisponible();
            $echecsThemeConsecutifs = 0;

            foreach ($chunks as $index => $chunk) {
                $donnees = [
                    'referentiel_id' => $this->referentiel->id,
                    'contenu' => $chunk['contenu'],
                    'position' => $index,
                    'page' => $chunk['page'] ?? null,
                    'article_reference' => $chunk['article'] ?? null,
                    'categorie_exigence' => $this->devinerCategorie($chunk['contenu']),
                    'taille_caracteres' => mb_strlen($chunk['contenu']),
                ];

                if ($themesActifs) {
                    $theme = $themeClassifier->classifier($chunk['contenu'], $chunk['article'] ?? null);
                    if ($theme !== null) {
                        $donnees['theme_dcp'] = $theme;
                        $echecsThemeConsecutifs = 0;
                    } else {
                        $echecsThemeConsecutifs++;
                        // Apres 3 echecs consecutifs (Ollama mort, timeouts), on
                        // arrete pour ne pas faire attendre l'admin 5 min sur
                        // chaque chunk. Les chunks restants auront theme_dcp=null
                        // et tomberont sur l'heuristique de secours.
                        if ($echecsThemeConsecutifs >= 3) {
                            Log::warning("Referentiel {$this->referentiel->id} : 3 echecs classification consecutifs, bascule en fallback heuristique pour les chunks restants.");
                            $themesActifs = false;
                        }
                    }
                }

                if ($embeddingsActives) {
                    try {
                        $donnees['embedding'] = $llm->genererEmbedding($chunk['contenu']);
                    } catch (\Throwable $e) {
                        Log::warning("Referentiel chunk {$index} embedding echoue — bascule des chunks suivants en fulltext", [
                            'error' => $e->getMessage(),
                        ]);
                        // Premier echec : on coupe court pour ne pas attendre 120s sur chaque chunk
                        $embeddingsActives = false;
                    }
                }

                ReferentielChunk::create($donnees);
            }

            Log::info("Referentiel {$this->referentiel->id} indexe : " . count($chunks) . ' exigences.');
        } catch (\Throwable $e) {
            Log::error("Erreur indexation referentiel {$this->referentiel->id} : {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Decoupe le texte en essayant de respecter la structure "Article X".
     * Si aucun marqueur d'article n'est detecte, fallback sur chunks fixes.
     */
    private function decouperParArticle(string $texte): array
    {
        $texteNettoye = preg_replace('/\[PAGE_BREAK:\d+\]/', '', $texte);

        // Detecter les articles : "Article 1", "Article 1.", "Art. 1", "ARTICLE PREMIER"
        $pattern = '/(Article\s+(?:premier|\d+(?:[\.\-]\d+)*)|Art\.\s*\d+(?:[\.\-]\d+)*)\s*[\.:\-]?/iu';

        if (! preg_match($pattern, $texteNettoye)) {
            return $this->decouperFixe($texteNettoye);
        }

        $morceaux = preg_split($pattern, $texteNettoye, -1, PREG_SPLIT_DELIM_CAPTURE);
        $chunks = [];
        $articleCourant = null;

        foreach ($morceaux as $morceau) {
            $morceau = trim($morceau);
            if (empty($morceau)) {
                continue;
            }

            if (preg_match($pattern, $morceau)) {
                $articleCourant = $morceau;
                continue;
            }

            // Limite a 2000 caracteres par exigence pour rester sous la taille contexte.
            // str_split coupe sur des bytes -> casse l'UTF-8 et coupe en plein mot
            // ("vites de fourniture" au lieu de "activites de fourniture").
            $sousChunks = mb_strlen($morceau) > 2000
                ? $this->splitSurMots($morceau, 2000)
                : [$morceau];

            $nbSous = count($sousChunks);
            foreach ($sousChunks as $sciIndex => $sc) {
                if (mb_strlen(trim($sc)) < 50) {
                    continue;
                }
                // Suffixe §N quand un article est decoupe en plusieurs sous-chunks,
                // sinon l'UI affiche 5 ecarts portant tous "Article 1" sans distinction.
                $refArticle = ($articleCourant && $nbSous > 1)
                    ? $articleCourant . ' §' . ($sciIndex + 1)
                    : $articleCourant;
                $chunks[] = [
                    'contenu' => trim($sc),
                    'article' => $refArticle,
                ];
            }
        }

        return $chunks;
    }

    /**
     * Decoupe un texte en blocs ≤ $taille caracteres en preferant les frontieres
     * naturelles (phrase > saut de ligne > espace), mb-safe.
     *
     * @return string[]
     */
    private function splitSurMots(string $texte, int $taille): array
    {
        $resultats = [];
        $longueur = mb_strlen($texte);
        $position = 0;

        while ($position < $longueur) {
            $finCible = min($position + $taille, $longueur);
            if ($finCible >= $longueur) {
                $resultats[] = mb_substr($texte, $position);
                break;
            }

            $segment = mb_substr($texte, $position, $taille);
            $seuilMin = (int) ($taille * 0.5);
            $fin = $finCible;

            $dernierPoint = mb_strrpos($segment, '.');
            if ($dernierPoint !== false && $dernierPoint > $seuilMin) {
                $fin = $position + $dernierPoint + 1;
            } else {
                $dernierSaut = mb_strrpos($segment, "\n");
                if ($dernierSaut !== false && $dernierSaut > $seuilMin) {
                    $fin = $position + $dernierSaut + 1;
                } else {
                    $dernierEspace = mb_strrpos($segment, ' ');
                    if ($dernierEspace !== false && $dernierEspace > $seuilMin) {
                        $fin = $position + $dernierEspace + 1;
                    }
                }
            }

            $resultats[] = mb_substr($texte, $position, $fin - $position);
            $position = $fin;
        }

        return $resultats;
    }

    /**
     * Fallback : chunks de taille fixe avec chevauchement, sur frontieres de mot.
     */
    private function decouperFixe(string $texte, int $taille = 1200, int $chevauchement = 200): array
    {
        $morceaux = $this->splitSurMots($texte, $taille);
        $chunks = [];
        foreach ($morceaux as $m) {
            $m = trim($m);
            if ($m !== '') {
                $chunks[] = ['contenu' => $m, 'article' => null];
            }
        }

        return $chunks;
    }

    /**
     * Devine la categorie de l'exigence selon les mots-cles.
     */
    private function devinerCategorie(string $texte): string
    {
        $texte = mb_strtolower($texte);

        $regles = [
            'technique' => ['chiffrement', 'cryptographie', 'protocole', 'serveur', 'base de donnees', 'systeme', 'securite technique', 'log', 'sauvegarde'],
            'gouvernance' => ['responsable du traitement', 'dpo', 'delegue', 'comite', 'gouvernance', 'direction', 'politique'],
            'juridique' => ['contrat', 'clause', 'consentement', 'sanction', 'penalite', 'droit', 'obligation legale'],
            'documentaire' => ['registre', 'documentation', 'procedure ecrite', 'declaration', 'rapport'],
            'organisationnelle' => ['formation', 'sensibilisation', 'organisation', 'processus', 'role'],
        ];

        foreach ($regles as $categorie => $motsCles) {
            foreach ($motsCles as $mot) {
                if (str_contains($texte, $mot)) {
                    return $categorie;
                }
            }
        }

        return 'autre';
    }
}
