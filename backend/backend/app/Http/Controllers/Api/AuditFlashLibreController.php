<?php

/**
 * AuditFlashLibreController — Audit Flash en self-service (Methode 3 libre).
 *
 * Le client peut lancer un Audit Flash directement depuis son compte,
 * sans qu'une mission ait ete creee par AS Consulting. Le questionnaire
 * est rattache au client (client_id) sans mission_id, et l'admin
 * AS Consulting peut consulter les resultats.
 *
 * Routes :
 *   GET    /api/client/audit-flash              : recupere (et cree si besoin) le questionnaire du client connecte
 *   POST   /api/client/audit-flash/reset        : reinitialise le questionnaire du client connecte
 *   GET    /api/admin/audit-flash-libres        : liste tous les Audit Flash libres (admin ASC)
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\QuestionnaireGenere;
use App\Services\Methode3\AuditFlashTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditFlashLibreController extends Controller
{
    /**
     * Cle de marqueur dans `themes` pour identifier un Audit Flash libre.
     */
    public const THEME_LIBRE = 'audit_flash_libre';

    /**
     * Recupere le questionnaire Audit Flash du client connecte. Si aucun
     * n'existe encore, le cree automatiquement avec le template fige.
     */
    public function showOuCreer(Request $request): JsonResponse
    {
        $client = $this->clientCourant($request);
        if (! $client) {
            return response()->json(['message' => 'Aucun client associé à votre compte.'], 404);
        }

        $questionnaire = $this->trouverQuestionnaireLibre($client->id);

        if (! $questionnaire) {
            $questionnaire = QuestionnaireGenere::create([
                'mission_id' => null,
                'client_id' => $client->id,
                'pole' => 'Audit Flash',
                'titre' => 'Audit Flash — Scan Pénal du Dirigeant',
                'description' => AuditFlashTemplate::description(),
                'questions' => AuditFlashTemplate::questions(),
                'source' => 'manuel',
                'themes' => array_merge(AuditFlashTemplate::themes(), [self::THEME_LIBRE]),
                'statut' => 'envoye',
                'genere_par' => $request->user()->id,
                'envoye_a' => now(),
            ]);
        }

        $questionnaire->load([
            'client:id,raison_sociale',
            'repondeur:id,nom,prenom',
        ]);

        return response()->json([
            'questionnaire' => $questionnaire,
            'client' => $client->only(['id', 'raison_sociale']),
        ]);
    }

    /**
     * Reinitialise le questionnaire Audit Flash libre du client (supprime
     * l'existant pour permettre de recommencer). N'affecte pas les Audit
     * Flash rattaches a une mission.
     */
    public function reset(Request $request): JsonResponse
    {
        $client = $this->clientCourant($request);
        if (! $client) {
            return response()->json(['message' => 'Aucun client associé à votre compte.'], 404);
        }

        QuestionnaireGenere::query()
            ->whereNull('mission_id')
            ->where('client_id', $client->id)
            ->whereJsonContains('themes', self::THEME_LIBRE)
            ->delete();

        return response()->json(['message' => 'Audit Flash réinitialisé.']);
    }

    /**
     * Liste tous les Audit Flash libres pour l'admin/manager/consultant.
     * Renvoie le score calcule, la zone de risque et la date de dernier
     * remplissage, pour faciliter la vue d'ensemble du portefeuille.
     */
    public function indexAdmin(Request $request): JsonResponse
    {
        $questionnaires = QuestionnaireGenere::query()
            ->whereNull('mission_id')
            ->whereNotNull('client_id')
            ->whereJsonContains('themes', self::THEME_LIBRE)
            ->with(['client:id,raison_sociale,sigle', 'repondeur:id,nom,prenom'])
            ->orderByDesc('rempli_a')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (QuestionnaireGenere $q) {
                $score = 0;
                $repondues = 0;
                $total = count($q->questions ?? []);
                $reponsesParNumero = collect($q->reponses ?? [])->keyBy(fn ($r) => (int) ($r['numero'] ?? 0));
                foreach (($q->questions ?? []) as $question) {
                    $numero = (int) ($question['numero'] ?? 0);
                    $reponse = (string) ($reponsesParNumero[$numero]['reponse'] ?? '');
                    if (trim($reponse) !== '') {
                        $repondues++;
                    }
                    $score += AuditFlashTemplate::scoreReponse($reponse);
                }

                if ($score <= 10) {
                    $zone = 'conforme';
                } elseif ($score <= 40) {
                    $zone = 'danger';
                } else {
                    $zone = 'rouge';
                }

                return [
                    'id' => $q->id,
                    'titre' => $q->titre,
                    'statut' => $q->statut,
                    'client' => $q->client,
                    'rempli_par' => $q->repondeur,
                    'rempli_a' => $q->rempli_a,
                    'created_at' => $q->created_at,
                    'updated_at' => $q->updated_at,
                    'total_questions' => $total,
                    'repondues' => $repondues,
                    'score_total' => $score,
                    'score_max' => $total * 10,
                    'zone' => $zone,
                ];
            });

        return response()->json(['data' => $questionnaires]);
    }

    /**
     * Recupere le premier client associe au user connecte.
     */
    private function clientCourant(Request $request): ?Client
    {
        $user = $request->user();
        $clientId = $user->clients()->pluck('clients.id')->first();
        if (! $clientId) {
            return null;
        }

        return Client::find($clientId);
    }

    /**
     * Trouve un Audit Flash libre existant pour un client donne.
     */
    private function trouverQuestionnaireLibre(int $clientId): ?QuestionnaireGenere
    {
        return QuestionnaireGenere::query()
            ->whereNull('mission_id')
            ->where('client_id', $clientId)
            ->whereJsonContains('themes', self::THEME_LIBRE)
            ->orderByDesc('created_at')
            ->first();
    }
}
