<?php

/**
 * Controller ValidationComptesController — Gestion des comptes en attente
 * de validation par AS Consulting.
 *
 * Workflow :
 *  - Un utilisateur s'inscrit via POST /register (cf. AuthController::register).
 *  - Son compte est cree avec compte_valide = false : impossible de se connecter.
 *  - Un admin/manager (permission validate-accounts) consulte la liste via
 *    GET /admin/comptes-en-attente.
 *  - Il approuve via POST /admin/comptes-en-attente/{user}/valider : le user
 *    passe a compte_valide = true et recoit un email de notification.
 *  - Il peut rejeter via POST /admin/comptes-en-attente/{user}/rejeter : le
 *    user est soft-delete (le compte reste tracable en cas de litige).
 */

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AccountRejectedMail;
use App\Mail\AccountValidatedMail;
use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ValidationComptesController extends Controller
{
    public function __construct(
        private AuditService $audit,
    ) {}

    /**
     * Liste les comptes en attente de validation (compte_valide = false).
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query()
            ->where('compte_valide', false)
            ->with(['role:id,name', 'clients:id,raison_sociale,sigle,statut'])
            ->orderByDesc('created_at');

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(fn ($sub) => $sub
                ->where('email', 'ilike', "%{$q}%")
                ->orWhere('nom', 'ilike', "%{$q}%")
                ->orWhere('prenom', 'ilike', "%{$q}%"));
        }

        $comptes = $query->paginate($request->integer('per_page', 20));

        return response()->json($comptes);
    }

    /**
     * Valide un compte en attente. Envoie un email de notification au user.
     */
    public function valider(Request $request, User $user): JsonResponse
    {
        if ($user->compte_valide) {
            return response()->json([
                'message' => 'Ce compte est déjà validé.',
            ], 422);
        }

        $user->update([
            'compte_valide' => true,
            'valide_le' => now(),
            'valide_par' => $request->user()->id,
        ]);

        // Email de notification (echec silencieux : ne pas bloquer la validation).
        try {
            Mail::to($user->email)->send(new AccountValidatedMail($user));
        } catch (\Throwable $e) {
            Log::warning("Email de validation non envoye pour user {$user->id}", [
                'error' => $e->getMessage(),
            ]);
        }

        $this->audit->log('compte.valide', [
            'user_id' => $user->id,
            'email' => $user->email,
            'valide_par' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Compte validé. Un e-mail a été envoyé à l\'utilisateur.',
            'user' => $user->fresh(['role:id,name', 'clients:id,raison_sociale']),
        ]);
    }

    /**
     * Rejette un compte en attente. Le motif est OBLIGATOIRE et sera transmis
     * au demandeur par email pour justifier la decision, lui permettre de
     * comprendre le refus et eventuellement de corriger sa demande.
     *
     * Le compte est soft-delete (traçabilite conservee en cas de litige).
     */
    public function rejeter(Request $request, User $user): JsonResponse
    {
        if ($user->compte_valide) {
            return response()->json([
                'message' => 'Impossible de rejeter un compte déjà validé. Utilisez la désactivation à la place.',
            ], 422);
        }

        $data = $request->validate([
            'motif' => 'required|string|min:10|max:2000',
        ], [
            'motif.required' => 'Le motif du refus est obligatoire (il sera transmis au demandeur).',
            'motif.min' => 'Le motif doit contenir au moins 10 caractères pour être explicite.',
            'motif.max' => 'Le motif ne peut pas dépasser 2000 caractères.',
        ]);

        $motif = trim($data['motif']);

        // Email au demandeur avec le motif (echec silencieux : ne pas bloquer
        // le rejet si le mail est indisponible ; on log pour tracer).
        $emailEnvoye = true;
        try {
            Mail::to($user->email)->send(new AccountRejectedMail($user, $motif));
        } catch (\Throwable $e) {
            $emailEnvoye = false;
            Log::warning("Email de rejet non envoye pour user {$user->id}", [
                'error' => $e->getMessage(),
            ]);
        }

        $this->audit->log('compte.rejete', [
            'user_id' => $user->id,
            'email' => $user->email,
            'rejete_par' => $request->user()->id,
            'motif' => $motif,
            'email_envoye' => $emailEnvoye,
        ]);

        $user->delete();

        return response()->json([
            'message' => $emailEnvoye
                ? 'Compte rejeté. Un e-mail explicatif a été envoyé au demandeur.'
                : 'Compte rejeté. L\'e-mail au demandeur n\'a pas pu être envoyé (voir les logs).',
            'email_envoye' => $emailEnvoye,
        ]);
    }
}
