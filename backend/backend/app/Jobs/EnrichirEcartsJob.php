<?php

/**
 * Job EnrichirEcartsJob — Enrichit les ecarts d'une analyse avec le LLM.
 *
 * Appele a posteriori sur une analyse en mode rapide pour faire rediger
 * des titre/description/recommandation par Ollama.
 *
 * Protections :
 *   - Timeout court par appel LLM (30s) via Http::timeout, pour eviter qu'un
 *     appel Ollama bloque le job plusieurs minutes.
 *   - Fallback silencieux si l'appel LLM echoue ou depasse : on garde le
 *     contenu existant et on passe au suivant.
 *   - Check d'annulation apres chaque ecart : si l'utilisateur clique
 *     "Annuler l'enrichissement", le job sort proprement.
 */

namespace App\Jobs;

use App\Models\Analyse;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EnrichirEcartsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Timeout en secondes par appel LLM (au-dela : fallback).
     *  llama3.2:3b sur CPU produit ~3-5 t/s ; pour 250 tokens il faut
     *  ~50-80s. Timeout 90s donne une marge sans bloquer. */
    private const LLM_TIMEOUT_SECONDS = 90;

    /** Marqueurs des titres "templates" (mode rapide) — utilises pour detecter
     *  un ecart non encore enrichi par LLM et eviter de retraiter ce qui l'a
     *  deja ete (idempotence). */
    private const PREFIXES_TEMPLATES = [
        'Absence de ',
        'Absence totale de ',
        'Preuve insuffisante de ',
        'Preuve incomplete de ',
        'Non-conformite : ',
    ];

    public int $tries = 1;
    public int $timeout = 7200;

    public function __construct(
        private Analyse $analyse,
        // Defauts UX : on n'enrichit QUE les ecarts critiques (les majeurs et
        // mineurs gardent leurs templates, deja tres lisibles). Sur llama3.2:3b
        // CPU (~5 t/s), 6 critiques x ~60s = ~5 min, contre 40+ min si on
        // enrichit tous les majeurs aussi. Le caller peut surcharger via
        // les booleens include_majeurs / include_mineurs.
        private bool $skipMineurs = true,
        private bool $skipMajeurs = true,
    ) {
        $this->onQueue('analyses');
    }

    public function handle(): void
    {
        // Le job tourne en dispatchAfterResponse : on libere le timeout PHP
        // et on ignore le ferme-onglet du client (le job continue en background).
        @set_time_limit(0);
        @ignore_user_abort(true);

        $this->analyse->update([
            'etape_courante' => 'Enrichissement IA : demarrage...',
            'progression_pct' => 0,
            'enrichissement_annule' => false,
        ]);

        // Charge tous les ecarts puis filtre selon les options :
        //   - skipMineurs (defaut true) : on saute les ecarts mineurs
        //   - on saute aussi les ecarts deja enrichis SI ET SEULEMENT SI
        //     l'analyse etait deja marquee enrichie avant ce run (= relance
        //     idempotente). Au premier passage post-rapide, les templates
        //     de GapAnalysisService produisent des titres qui ressemblent
        //     a du LLM-enrichi, donc on ne se fie pas a l'heuristique titre.
        $tousEcarts = $this->analyse->ecarts()->with('referentielChunk')->get();
        $analyseDejaEnrichie = (bool) $this->analyse->enrichissement_ia;
        $aEnrichir = $tousEcarts->filter(function ($e) use ($analyseDejaEnrichie) {
            if ($this->skipMineurs && $e->gravite === 'mineur') return false;
            if ($this->skipMajeurs && $e->gravite === 'majeur') return false;
            if ($analyseDejaEnrichie && $this->dejaEnrichi($e)) return false;
            return true;
        })->values();

        $totalGeneral = $tousEcarts->count();
        $totalATraiter = $aEnrichir->count();
        $totalIgnores = $totalGeneral - $totalATraiter;

        if ($totalGeneral === 0) {
            $this->analyse->update([
                'etape_courante' => 'Enrichissement IA : aucun ecart',
                'progression_pct' => 100,
                'enrichissement_ia' => true,
            ]);
            return;
        }
        if ($totalATraiter === 0) {
            $this->analyse->update([
                'etape_courante' => "Enrichissement IA : tous les ecarts deja enrichis ({$totalIgnores})",
                'progression_pct' => 100,
                'enrichissement_ia' => true,
            ]);
            return;
        }

        $baseUrl = config('services.ollama.host', 'http://127.0.0.1:11434');
        $modele = config('services.ollama.model', 'llama3.2');

        $i = 0;
        foreach ($aEnrichir as $ecart) {
            $i++;

            // Check annulation avant chaque ecart (state fresh depuis la DB)
            if ($this->analyse->fresh()->enrichissement_annule) {
                Log::info("Enrichissement IA analyse {$this->analyse->id} : annule par l'utilisateur a {$i}/{$totalATraiter}.");
                $this->analyse->update([
                    'etape_courante' => sprintf('Enrichissement IA : annule (%d/%d traites)', $i - 1, $totalATraiter),
                    'progression_pct' => (int) round((($i - 1) / $totalATraiter) * 100),
                ]);
                return;
            }

            $exigence = $ecart->referentielChunk;
            if (! $exigence) {
                $this->mettreAJourProgression($i, $totalATraiter, $totalIgnores);
                continue;
            }

            $articleRef = $ecart->article_reference ?: 'cette exigence';
            $extraitClient = $ecart->extrait_document
                ? mb_substr($ecart->extrait_document, 0, 800) // tronque pour gagner ~30% sur le prompt
                : '(aucun document pertinent trouve cote client)';
            // Tronque aussi l'exigence (les chunks ARTCI font parfois 1500+ chars).
            $exigenceTronquee = mb_substr($ecart->exigence_referentiel, 0, 600);

            // Prompt resserre : exemple retire (gain ~200 tokens). Les regles
            // sont conservees mais condensees pour reduire le temps de
            // generation Ollama (lineaire en tokens entree + sortie).
            $prompt = <<<PROMPT
Consultant DCP Côte d'Ivoire (ARTCI, loi 2013-450). Rédige un constat **précis, actionnable, accentué**. INTERDIT : "fournir ou mettre à jour la documentation".

EXIGENCE ({$articleRef}) : {$exigenceTronquee}
TYPE D'ÉCART : {$ecart->type_ecart}
EXTRAIT CLIENT : {$extraitClient}

JSON STRICT une ligne, accents corrects, sans markdown :
{"titre":"15 mots max, concept manquant","description":"2-3 phrases citant CE qui manque","risque":"1-2 phrases (sanction ARTCI, droits, conservation…)","recommandation":"action concrète : QUOI mettre en place + AVEC QUEL CONTENU"}
PROMPT;

            try {
                // Appel Ollama direct avec timeout strict
                $response = Http::timeout(self::LLM_TIMEOUT_SECONDS)
                    ->connectTimeout(5)
                    ->post("{$baseUrl}/api/chat", [
                        'model' => $modele,
                        'messages' => [['role' => 'user', 'content' => $prompt]],
                        'stream' => false,
                        'options' => [
                            'temperature' => 0.2,
                            'num_predict' => 250, // 4 champs JSON tres courts ; ~50s a 5 t/s
                        ],
                    ]);

                if ($response->successful()) {
                    $contenu = $response->json('message.content') ?? '';
                    if (preg_match('/\{[\s\S]*\}/', $contenu, $m)) {
                        $json = json_decode($m[0], true);
                        if (is_array($json) && isset($json['titre'])) {
                            $ecart->update([
                                'titre' => mb_substr(trim($json['titre']), 0, 255),
                                'description_ecart' => trim($json['description'] ?? '') ?: $ecart->description_ecart,
                                'risque' => trim($json['risque'] ?? '') ?: $ecart->risque,
                                'recommandation' => trim($json['recommandation'] ?? '') ?: $ecart->recommandation,
                            ]);
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Timeout, refus, erreur JSON... on ignore et passe au suivant
                Log::warning("Enrichissement ecart {$ecart->id} ignore", ['error' => $e->getMessage()]);
            }

            $this->mettreAJourProgression($i, $totalATraiter, $totalIgnores);
        }

        $msg = "Enrichissement IA termine ({$totalATraiter} traites";
        if ($totalIgnores > 0) {
            $msg .= ", {$totalIgnores} ignores (mineurs ou deja enrichis)";
        }
        $msg .= ')';
        $this->analyse->update([
            'enrichissement_ia' => true,
            'etape_courante' => $msg,
            'progression_pct' => 100,
        ]);
    }

    /**
     * Considere un ecart comme deja enrichi par LLM si son titre ne commence
     * pas par un des prefixes "templates" du mode rapide ET qu'il a une
     * description redigee (pas vide). Permet de relancer l'enrichissement
     * sans repayer le cout LLM des ecarts deja traites.
     */
    private function dejaEnrichi(\App\Models\Ecart $ecart): bool
    {
        $titre = trim((string) $ecart->titre);
        if ($titre === '') return false;
        foreach (self::PREFIXES_TEMPLATES as $prefix) {
            if (str_starts_with($titre, $prefix)) return false;
        }
        return trim((string) $ecart->description_ecart) !== '';
    }

    private function mettreAJourProgression(int $i, int $total, int $ignores = 0): void
    {
        $pct = (int) round(($i / $total) * 100);
        $suffix = $ignores > 0 ? " (+{$ignores} ignores)" : '';
        $this->analyse->update([
            'progression_pct' => $pct,
            'etape_courante' => "Enrichissement IA : {$i}/{$total} ecarts{$suffix}",
        ]);
    }
}
