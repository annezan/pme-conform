<?php

/**
 * Job ProcessDocumentJob — Traitement asynchrone des documents.
 *
 * Pipeline : extraction texte → decoupe en chunks → embeddings (si pgvector) → stockage.
 * Fonctionne meme sans pgvector : les chunks sont crees sans embeddings
 * et restent interrogeables par recherche full-text.
 */

namespace App\Jobs;

use App\Contracts\LLMConnectorInterface;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Services\Document\ExtractorFactory;
use App\Services\Document\QuestionnaireParser;
use App\Services\RAG\PgvectorChecker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600;

    public function __construct(
        private Document $document,
    ) {
        $this->onQueue('documents');
    }

    public function handle(
        LLMConnectorInterface $llm,
        ExtractorFactory $extractorFactory,
        PgvectorChecker $pgvectorChecker,
        QuestionnaireParser $questionnaireParser,
    ): void {
        // Job execute aussi en `dispatchAfterResponse` (mode sans worker).
        @set_time_limit(0);
        @ignore_user_abort(true);

        try {
            $this->document->update(['statut' => 'en_traitement']);

            // Etape 1 : Extraire le texte si pas encore fait
            $contenu = $this->document->contenu_extrait;
            if (empty($contenu)) {
                $contenu = $this->extraireTexte($extractorFactory);
                if (empty($contenu)) {
                    $this->document->update(['statut' => 'erreur']);
                    Log::error("Document {$this->document->id} : extraction de texte echouee.");

                    return;
                }
                $this->document->update(['contenu_extrait' => $contenu]);
            }

            // Etape 1bis : Detection questionnaire + extraction Q/R
            $analyseQuestionnaire = $questionnaireParser->analyser($contenu);
            $this->document->update([
                'is_questionnaire' => $analyseQuestionnaire['is_questionnaire'],
                'nb_questions' => $analyseQuestionnaire['nb_questions'],
                'nb_questions_repondues' => $analyseQuestionnaire['nb_questions_repondues'],
                'questions_data' => $analyseQuestionnaire['questions_data'],
            ]);
            if ($analyseQuestionnaire['is_questionnaire']) {
                Log::info("Document {$this->document->id} : questionnaire detecte — {$analyseQuestionnaire['nb_questions']} questions dont {$analyseQuestionnaire['nb_questions_repondues']} repondues.");
            }

            // Etape 2 : Decouper en chunks
            // - Si questionnaire : 1 chunk par question (Q + R combinees) pour precision
            // - Sinon : decoupe classique par taille fixe avec pagination
            $chunks = $analyseQuestionnaire['is_questionnaire']
                ? $this->decouperQuestionnaire($analyseQuestionnaire['questions_data'])
                : $this->decouper($contenu);

            // Etape 3 : Sauvegarder les chunks (avec ou sans embeddings)
            $pgvectorDisponible = $pgvectorChecker->estDisponible();

            foreach ($chunks as $index => $chunk) {
                $donnees = [
                    'document_id' => $this->document->id,
                    'contenu' => $chunk['contenu'],
                    'position' => $index,
                    'page' => $chunk['page'] ?? null,
                    'taille_caracteres' => mb_strlen($chunk['contenu']),
                    'metadata' => $chunk['metadata'] ?? null,
                ];

                // Generer l'embedding uniquement si pgvector est disponible
                if ($pgvectorDisponible) {
                    try {
                        $donnees['embedding'] = $llm->genererEmbedding($chunk['contenu']);
                    } catch (\Throwable $e) {
                        Log::warning("Document {$this->document->id} chunk {$index} : embedding echoue", [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                DocumentChunk::create($donnees);
            }

            $this->document->update(['statut' => 'indexe']);

            $nbChunks = $this->document->chunks()->count();
            $mode = $pgvectorDisponible ? 'pgvector' : 'full-text';
            Log::info("Document {$this->document->id} traite : {$this->document->titre} ({$nbChunks} chunks, mode {$mode})");

        } catch (\Throwable $e) {
            $this->document->update(['statut' => 'erreur']);
            Log::error("Erreur traitement document {$this->document->id} : {$e->getMessage()}");

            throw $e;
        }
    }

    /**
     * Extrait le texte du fichier attache au document via Spatie Media Library.
     */
    private function extraireTexte(ExtractorFactory $extractorFactory): string
    {
        $media = $this->document->getFirstMedia('fichiers');
        if (! $media) {
            Log::error("Document {$this->document->id} : aucun fichier attache.");

            return '';
        }

        if (! $extractorFactory->supporte($this->document->type_mime)) {
            Log::error("Document {$this->document->id} : type MIME non supporte ({$this->document->type_mime}).");

            return '';
        }

        return $extractorFactory->extraire($media->getPath(), $this->document->type_mime);
    }

    /**
     * Decoupe le texte en chunks avec chevauchement et suivi de pagination.
     */
    private function decouper(string $texte, int $tailleChunk = 1000, int $chevauchement = 200): array
    {
        // Extraire les marqueurs de page inseres par l'extracteur PDF
        $pageActuelle = 1;
        $texteNettoye = preg_replace_callback(
            '/\[PAGE_BREAK:(\d+)\]/',
            function ($matches) use (&$pageActuelle) {
                $pageActuelle = (int) $matches[1];

                return '';
            },
            $texte
        );

        $chunks = [];
        $longueur = mb_strlen($texteNettoye);
        $position = 0;

        while ($position < $longueur) {
            $fin = min($position + $tailleChunk, $longueur);

            // Couper sur une frontiere naturelle : phrase > saut de ligne > espace
            if ($fin < $longueur) {
                $fin = $this->trouverCoupureNaturelle($texteNettoye, $position, $tailleChunk);
            }

            $contenuChunk = trim(mb_substr($texteNettoye, $position, $fin - $position));
            if (! empty($contenuChunk)) {
                // Estimer la page du chunk en fonction de sa position dans le texte original
                $pageEstimee = $this->estimerPage($texte, $position);
                $chunks[] = [
                    'contenu' => $contenuChunk,
                    'page' => $pageEstimee,
                ];
            }

            // Sortir si on a atteint la fin du texte (evite boucle infinie
            // quand $fin = $longueur et $chevauchement > 0)
            if ($fin >= $longueur) {
                break;
            }

            $nouvellePosition = $fin - $chevauchement;
            // Garantir une progression minimale pour eviter les boucles infinies
            if ($nouvellePosition <= $position) {
                $nouvellePosition = $position + max(1, $tailleChunk - $chevauchement);
            }
            // Avancer jusqu'a la prochaine frontiere de mot pour ne pas commencer
            // un chunk au milieu d'un mot ("latifs au traitement" au lieu de "relatifs...").
            $position = $this->avancerJusquAuMot($texteNettoye, $nouvellePosition);
        }

        return $chunks;
    }

    /**
     * Cherche la meilleure frontiere de coupure dans la fenetre [position, position+tailleChunk] :
     * fin de phrase ('.') > saut de ligne > espace > coupure brute en dernier recours.
     */
    private function trouverCoupureNaturelle(string $texte, int $position, int $tailleChunk): int
    {
        $longueur = mb_strlen($texte);
        $segment = mb_substr($texte, $position, $tailleChunk);
        $seuilMin = (int) ($tailleChunk * 0.5);

        $dernierPoint = mb_strrpos($segment, '.');
        if ($dernierPoint !== false && $dernierPoint > $seuilMin) {
            return $position + $dernierPoint + 1;
        }

        $dernierSaut = mb_strrpos($segment, "\n");
        if ($dernierSaut !== false && $dernierSaut > $seuilMin) {
            return $position + $dernierSaut + 1;
        }

        $dernierEspace = mb_strrpos($segment, ' ');
        if ($dernierEspace !== false && $dernierEspace > $seuilMin) {
            return $position + $dernierEspace + 1;
        }

        return min($position + $tailleChunk, $longueur);
    }

    /**
     * Avance la position jusqu'au prochain espace si on est en plein milieu d'un mot.
     * Limite a 80 caracteres pour ne pas perdre de contenu utile.
     */
    private function avancerJusquAuMot(string $texte, int $position): int
    {
        $longueur = mb_strlen($texte);
        if ($position <= 0 || $position >= $longueur) {
            return max(0, min($position, $longueur));
        }

        $precedent = mb_substr($texte, $position - 1, 1);
        $courant = mb_substr($texte, $position, 1);
        if (preg_match('/\s/u', $precedent) || preg_match('/\s/u', $courant)) {
            return $position;
        }

        for ($i = 1; $i <= 80 && $position + $i < $longueur; $i++) {
            if (preg_match('/\s/u', mb_substr($texte, $position + $i, 1))) {
                return $position + $i + 1;
            }
        }

        return $position;
    }

    /**
     * Estime le numero de page en comptant les marqueurs [PAGE_BREAK:N] avant la position.
     */
    private function estimerPage(string $texteOriginal, int $position): int
    {
        $prefixe = mb_substr($texteOriginal, 0, $position);
        preg_match_all('/\[PAGE_BREAK:(\d+)\]/', $prefixe, $matches);

        if (! empty($matches[1])) {
            return (int) end($matches[1]);
        }

        return 1;
    }

    /**
     * Decoupe un questionnaire : 1 chunk par question (Q + R concatenees).
     * Permet une recherche semantique precise et un rattachement au N° de question.
     */
    /**
     * Tronque un texte sur frontiere de mot avec ellipsis si coupure.
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

    private function decouperQuestionnaire(array $questionsData): array
    {
        $chunks = [];
        foreach ($questionsData as $q) {
            $numero = $q['numero'] ?? 0;
            $question = trim($q['question'] ?? '');
            $reponse = trim($q['reponse'] ?? '');
            $repondu = $q['repondu'] ?? false;

            // Texte combine pour indexation
            $texte = "Question {$numero} : {$question}\nReponse : " . ($repondu ? $reponse : '(non repondu)');

            if (mb_strlen(trim($texte)) < 20) {
                continue;
            }

            $chunks[] = [
                'contenu' => $texte,
                'page' => null,
                'metadata' => [
                    'is_questionnaire' => true,
                    'question_numero' => $numero,
                    'question_texte' => $this->tronquerSurMot($question, 500),
                    'reponse_texte' => $this->tronquerSurMot($reponse, 500),
                    'repondu' => $repondu,
                ],
            ];
        }

        return $chunks;
    }
}
