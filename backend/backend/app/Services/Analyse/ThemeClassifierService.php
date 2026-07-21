<?php

/**
 * Service ThemeClassifierService — Classe un chunk de referentiel par theme DCP.
 *
 * Strategie : appel LLM court (prompt minimaliste, 1 seul mot en sortie) au moment
 * de l'indexation du referentiel. Le resultat est persiste dans la colonne
 * referentiel_chunks.theme_dcp et reutilise sans cout par GapAnalysisService.
 *
 * Pourquoi : l'heuristique par mots-cles est trop fragile sur les textes
 * juridiques (le mot "responsable du traitement" / "ARTCI" apparait partout
 * et ecrase le vrai theme). Un LLM, meme petit (llama3.2:3b), gere bien la
 * classification one-shot avec un prompt structure.
 *
 * Tolerance : si le LLM est indisponible ou repond mal, on renvoie null —
 * GapAnalysisService bascule alors sur l'ancienne heuristique en fallback,
 * et la classification pourra etre rejouee plus tard.
 */

namespace App\Services\Analyse;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ThemeClassifierService
{
    /** Themes valides (doit rester aligne avec templatesParTheme de GapAnalysisService). */
    public const THEMES_VALIDES = [
        'donnees_sensibles', 'conservation', 'consentement', 'dpo', 'transfert',
        'securite', 'registre', 'droits', 'sous_traitance', 'aipd', 'information',
        'notification_violation', 'responsable_traitement', 'formalites_artci',
        'principes', 'mineurs', 'marketing', 'portabilite', 'autre',
    ];

    /** Timeout par appel : llama3.2:3b CPU passe ~80% du temps en prompt_eval.
     *  90s pour absorber le pire cas (chunk de 250 chars + prompt ~150 tokens). */
    private const LLM_TIMEOUT_SECONDS = 90;

    /**
     * Classifie un chunk de referentiel et renvoie le theme DCP, ou null si echec.
     *
     * Le caller (job d'indexation) decide quoi faire en cas de null : laisser
     * la colonne null (heuristique fallback) ou retenter plus tard.
     */
    public function classifier(string $contenu, ?string $articleReference = null): ?string
    {
        $baseUrl = config('services.ollama.host', 'http://127.0.0.1:11434');
        $modele = config('services.ollama.model', 'llama3.2');

        // Prompt ULTRA court : sur llama3.2:3b CPU, le prompt_eval domine le temps
        // d'execution (~5 t/s en entree). Chaque mot d'instruction coute. On
        // tronque le chunk a 250 chars (l'essentiel d'un article tient dans la
        // premiere phrase) et on liste les themes sans regles detaillees.
        $extrait = mb_substr(trim($contenu), 0, 250);

        $prompt = <<<PROMPT
Tu reponds UNIQUEMENT par UN seul mot, sans explication.

Liste de themes : donnees_sensibles, consentement, conservation, dpo, droits, transfert, securite, registre, sous_traitance, aipd, information, notification_violation, responsable_traitement, formalites_artci, principes, mineurs, marketing, portabilite, autre.

Texte juridique DCP : {$extrait}

Quel theme correspond ? Reponds par un seul mot de la liste, rien d'autre.
PROMPT;

        try {
            $response = Http::timeout(self::LLM_TIMEOUT_SECONDS)
                ->connectTimeout(5)
                ->post("{$baseUrl}/api/chat", [
                    'model' => $modele,
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                    'stream' => false,
                    'options' => [
                        'temperature' => 0.0, // deterministe — meme chunk = meme theme
                        'num_predict' => 20,  // marge pour absorber "securite." ou "Le theme est X"
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('ThemeClassifier : Ollama non-200', [
                    'status' => $response->status(),
                    'article' => $articleReference,
                ]);
                return null;
            }

            $contenuReponse = $response->json('message.content') ?? '';
            return $this->extraireThemeValide($contenuReponse);
        } catch (\Throwable $e) {
            Log::warning('ThemeClassifier : exception', [
                'error' => $e->getMessage(),
                'article' => $articleReference,
            ]);
            return null;
        }
    }

    /**
     * Extrait un theme valide de la reponse brute du LLM.
     *
     * Le LLM peut renvoyer "securite", "Securite.", "Le theme est securite",
     * "**securite**", etc. On normalise et on cherche un mot de la liste valide.
     */
    private function extraireThemeValide(string $reponse): ?string
    {
        $reponse = mb_strtolower(trim($reponse));
        // Strip markdown, ponctuation, accents francais minimaux
        $reponse = strtr($reponse, [
            'à'=>'a','â'=>'a','é'=>'e','è'=>'e','ê'=>'e','î'=>'i','ô'=>'o','û'=>'u','ç'=>'c',
            '*'=>' ', '"'=>' ', "'"=>' ', '.'=>' ', ','=>' ', ':'=>' ', ';'=>' ', "\n"=>' ',
        ]);

        // Cherche le premier mot de la liste qui apparait dans la reponse.
        // On trie par longueur decroissante pour matcher "notification_violation"
        // avant "notification" hypothetique.
        $themesTries = self::THEMES_VALIDES;
        usort($themesTries, fn ($a, $b) => mb_strlen($b) - mb_strlen($a));

        foreach ($themesTries as $theme) {
            if (str_contains($reponse, $theme)) {
                return $theme;
            }
        }

        return null;
    }
}
