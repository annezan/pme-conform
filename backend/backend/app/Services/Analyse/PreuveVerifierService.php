<?php

/**
 * Service PreuveVerifierService — Verifie si une preuve client satisfait
 * une recommandation d'ecart, via appel LLM.
 *
 * Use case : apres que le client a uploade ses preuves sur les items d'un
 * plan d'action et clique "Soumettre", on compare CHAQUE preuve avec la
 * recommandation de l'ecart lie a l'item. Le LLM evalue si le contenu
 * documentaire repond a l'exigence formulee.
 *
 * Le but n'est PAS de detecter de nouveaux ecarts (cf. AnalyseController::refaire),
 * mais de produire un verdict cible :
 *   - conforme : la preuve repond pleinement a la recommandation
 *   - partielle : la preuve traite le sujet mais incomplete
 *   - non_conforme : la preuve ne repond pas a la recommandation
 *
 * Le LLM doit aussi produire une justification courte (1-2 phrases) qui
 * explique le verdict au consultant.
 */

namespace App\Services\Analyse;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PreuveVerifierService
{
    public const VERDICTS_VALIDES = ['conforme', 'partielle', 'non_conforme'];

    /** Timeout par appel. La generation est plus longue que la classification
     *  (le LLM produit verdict + justification). 120s pour absorber les pics. */
    private const LLM_TIMEOUT_SECONDS = 120;

    /**
     * Verifie une preuve face a une recommandation.
     *
     * @return array{verdict: ?string, justification: ?string, brut: ?string}
     *         verdict null = echec ou reponse non parsable.
     */
    public function verifier(
        string $recommandation,
        string $contenuPreuve,
        ?string $exigenceReferentiel = null,
    ): array {
        $baseUrl = config('services.ollama.host', 'http://127.0.0.1:11434');
        $modele = config('services.ollama.model', 'llama3.2');

        // Tronque agressivement le contenu de la preuve : llama3.2:3b sur CPU
        // est lineaire dans la taille du prompt (~5 t/s). 2000 chars = ~500 tokens
        // d'entree, on reste sous les 90s.
        $extraitPreuve = mb_substr(trim($contenuPreuve), 0, 2000);
        $recoTronquee = mb_substr(trim($recommandation), 0, 800);
        $exigenceLine = $exigenceReferentiel
            ? "EXIGENCE DU REFERENTIEL : " . mb_substr(trim($exigenceReferentiel), 0, 500) . "\n\n"
            : '';

        $prompt = <<<PROMPT
Tu es auditeur de conformite DCP. Tu evalues si une PREUVE documentaire (fournie par le client) repond a une RECOMMANDATION (formulee par le consultant).

{$exigenceLine}RECOMMANDATION A SATISFAIRE :
{$recoTronquee}

PREUVE FOURNIE PAR LE CLIENT (extrait du document) :
{$extraitPreuve}

Reponds en JSON STRICT, une seule ligne, sans markdown :
{"verdict":"conforme|partielle|non_conforme","justification":"1 a 2 phrases qui expliquent le verdict, mentionnent ce qui est present ou absent dans la preuve par rapport a la recommandation"}

Regles :
- "conforme" : la preuve traite explicitement TOUS les elements demandes par la recommandation.
- "partielle" : la preuve aborde le sujet mais des elements essentiels manquent.
- "non_conforme" : la preuve ne repond pas a la recommandation, parle d'autre chose ou est trop vague.
- Justification factuelle, sans formules vagues. Cite les elements presents ou absents.
PROMPT;

        try {
            $response = Http::timeout(self::LLM_TIMEOUT_SECONDS)
                ->connectTimeout(5)
                ->post("{$baseUrl}/api/chat", [
                    'model' => $modele,
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                    'stream' => false,
                    'options' => [
                        'temperature' => 0.1, // legere variabilite pour eviter les sorties degeneres
                        'num_predict' => 250,
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('PreuveVerifier : Ollama non-200', [
                    'status' => $response->status(),
                ]);
                return ['verdict' => null, 'justification' => null, 'brut' => null];
            }

            $brut = $response->json('message.content') ?? '';
            return $this->extraireVerdict($brut);
        } catch (\Throwable $e) {
            Log::warning('PreuveVerifier : exception', ['error' => $e->getMessage()]);
            return ['verdict' => null, 'justification' => null, 'brut' => null];
        }
    }

    /**
     * Extrait verdict + justification de la reponse LLM.
     * Tolerance : la reponse peut etre entouree de markdown, contenir des
     * espaces parasites, ou avoir un JSON legerement malforme.
     */
    private function extraireVerdict(string $brut): array
    {
        $defaut = ['verdict' => null, 'justification' => null, 'brut' => $brut];

        if (! preg_match('/\{[\s\S]*\}/', $brut, $m)) {
            return $defaut;
        }

        $json = json_decode($m[0], true);
        if (! is_array($json)) {
            return $defaut;
        }

        $verdict = mb_strtolower(trim((string) ($json['verdict'] ?? '')));
        // Normalisations courantes du LLM
        $verdict = strtr($verdict, ['é' => 'e', 'è' => 'e', 'ê' => 'e']);
        if (! in_array($verdict, self::VERDICTS_VALIDES, true)) {
            // Tentative de matching partiel : "non-conforme" / "non conforme"
            foreach (self::VERDICTS_VALIDES as $v) {
                if (str_contains($verdict, $v)) {
                    $verdict = $v;
                    break;
                }
            }
        }
        if (! in_array($verdict, self::VERDICTS_VALIDES, true)) {
            return $defaut;
        }

        $justification = trim((string) ($json['justification'] ?? ''));
        if ($justification === '') {
            $justification = 'Aucune justification fournie par le moteur.';
        }
        // Plafond raisonnable pour eviter d'exploser la colonne en DB
        $justification = mb_substr($justification, 0, 2000);

        return [
            'verdict' => $verdict,
            'justification' => $justification,
            'brut' => $brut,
        ];
    }
}
