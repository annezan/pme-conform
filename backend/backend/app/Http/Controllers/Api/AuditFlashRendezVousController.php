<?php

/**
 * AuditFlashRendezVousController — Prise de rendez-vous a l'issue d'un audit flash.
 *
 *   POST /audit-flash/rendez-vous          : soumission publique (ou client) d'une demande
 *   GET  /admin/audit-flash/rendez-vous    : liste pour AS Consulting
 *   PUT  /admin/audit-flash/rendez-vous/{id} : mise a jour statut/notes
 *
 * La soumission envoie un email a la boite centrale (services.asc.contact_email)
 * et notifie tous les utilisateurs ayant le role admin.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\AuditFlashRendezVousDemande;
use App\Models\AuditFlashRendezVous;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AuditFlashRendezVousController extends Controller
{
    /**
     * Soumet une nouvelle demande de rendez-vous.
     * Accessible aux clients connectes ET aux visiteurs anonymes ayant rempli l'audit flash.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nom' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'telephone' => 'nullable|string|max:30',
            'creneau_souhaite' => 'nullable|date',
            'creneau_libelle' => 'nullable|string|max:255',
            'type_demande' => 'required|in:accompagnement,audit_complet',
            'message' => 'nullable|string|max:5000',
            'mission_id' => 'nullable|exists:missions,id',
            'questionnaire_genere_id' => 'nullable|exists:questionnaires_generes,id',
        ]);

        $user = $request->user();
        $clientId = null;
        if ($user) {
            $data['user_id'] = $user->id;
            $clientId = $user->clients()->value('clients.id');
        }
        if ($clientId) {
            $data['client_id'] = $clientId;
        }

        $rdv = AuditFlashRendezVous::create($data);

        $this->notifierAsConsulting($rdv);

        return response()->json([
            'message' => 'Votre demande a été transmise. AS Consulting vous recontactera très prochainement.',
            'rendez_vous' => $rdv,
        ], 201);
    }

    /**
     * Vue admin/manager/consultant : portefeuille des demandes.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasPermissionTo('view-portefeuille')) {
            abort(403, 'Action reservee aux utilisateurs internes.');
        }

        $query = AuditFlashRendezVous::query()
            ->with(['client:id,raison_sociale', 'mission:id,reference', 'utilisateur:id,nom,prenom', 'assignee:id,nom,prenom']);

        if ($request->filled('statut')) {
            $query->where('statut', $request->input('statut'));
        }
        if ($request->filled('type_demande')) {
            $query->where('type_demande', $request->input('type_demande'));
        }

        return response()->json($query->latest()->paginate($request->integer('per_page', 15)));
    }

    public function show(Request $request, AuditFlashRendezVous $rendezVous): JsonResponse
    {
        if (! $request->user()->hasPermissionTo('view-portefeuille')) {
            abort(403, 'Action reservee aux utilisateurs internes.');
        }
        $rendezVous->load(['client', 'mission', 'utilisateur:id,nom,prenom', 'assignee:id,nom,prenom']);

        return response()->json(['rendez_vous' => $rendezVous]);
    }

    public function update(Request $request, AuditFlashRendezVous $rendezVous): JsonResponse
    {
        if (! $request->user()->hasPermissionTo('view-portefeuille')) {
            abort(403, 'Action reservee aux utilisateurs internes.');
        }

        $data = $request->validate([
            'statut' => 'nullable|in:nouveau,contacte,planifie,realise,annule',
            'notes_internes' => 'nullable|string|max:5000',
            'assigne_a' => 'nullable|exists:users,id',
            'contacte_at' => 'nullable|date',
        ]);

        if (($data['statut'] ?? null) === 'contacte' && empty($data['contacte_at']) && ! $rendezVous->contacte_at) {
            $data['contacte_at'] = now();
        }

        $rendezVous->update($data);

        return response()->json(['rendez_vous' => $rendezVous->fresh()]);
    }

    /**
     * Envoie un email a la boite centrale + a tous les utilisateurs admin.
     * Capte les erreurs SMTP pour ne pas bloquer la soumission du formulaire.
     */
    private function notifierAsConsulting(AuditFlashRendezVous $rdv): void
    {
        $destinataires = [];

        $contactCentral = config('services.asc.contact_email');
        if ($contactCentral) {
            $destinataires[] = $contactCentral;
        }

        // Le projet n'utilise pas le trait Spatie HasRoles sur User : on a une
        // relation BelongsTo role() via la colonne role_id. On filtre donc par
        // jointure sur le nom du role.
        $adminsEmails = User::query()
            ->whereHas('role', fn ($q) => $q->where('name', 'admin'))
            ->pluck('email')
            ->filter()
            ->all();
        $destinataires = array_values(array_unique(array_merge($destinataires, $adminsEmails)));

        if (empty($destinataires)) {
            return;
        }

        try {
            Mail::to($destinataires)->send(new AuditFlashRendezVousDemande($rdv));
        } catch (\Throwable $e) {
            Log::warning('Echec notification RDV audit flash : ' . $e->getMessage(), [
                'rendez_vous_id' => $rdv->id,
            ]);
        }
    }
}
