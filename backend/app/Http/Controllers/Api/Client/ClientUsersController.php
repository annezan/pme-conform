<?php

/**
 * ClientUsersController — Gestion des utilisateurs d'une entreprise cliente
 * par le client_admin.
 *
 * Le client_admin connecte peut :
 *   - Lister les utilisateurs de SON entreprise (avec leur pole)
 *   - Creer un nouvel utilisateur (mot de passe temporaire genere + email)
 *   - Modifier les infos d'un utilisateur (nom, prenom, pole)
 *   - Reinitialiser le mot de passe d'un utilisateur (nouveau temp + email)
 *   - Activer / Desactiver un utilisateur
 *   - Supprimer un utilisateur (soft-delete)
 *
 * Toutes les operations sont strictement scopees a l'entreprise du client_admin :
 *   impossible de toucher aux utilisateurs d'une autre entreprise.
 */

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Mail\NewUserCredentialsMail;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\Methode2\MatriceTemplate;
use App\Services\Security\GenerateurMotDePasse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class ClientUsersController extends Controller
{
    public function __construct(
        private AuditService $audit,
    ) {}

    /**
     * Liste les services connus pour ce client, derives de l'organigramme.
     * Chaque entree porte son pole parent (lever l'ambiguite des services qui
     * portent le meme nom sur plusieurs poles, type "Direction Technique
     * Centrale" qui peut etre dans Pole 1 et Pole 3).
     *
     * Forme : [{ pole, service, libelle, user_id?, user_libelle? }]
     */
    public function services(Request $request): JsonResponse
    {
        $client = $this->clientCourant($request);
        if (! $client) {
            return response()->json(['data' => []]);
        }

        $sources = collect();
        // Services issus des organigrammes du client
        $missions = $client->missions()->with('organigramme')->get();
        foreach ($missions as $mission) {
            foreach (($mission->organigramme?->structure ?? []) as $entree) {
                $pole = trim((string) ($entree['pole'] ?? ''));
                if ($pole === '') continue;
                foreach (($entree['services'] ?? []) as $svc) {
                    $nom = trim((string) ($svc['nom'] ?? ''));
                    if ($nom !== '') {
                        $sources->push(['pole' => $pole, 'service' => $nom]);
                    }
                }
            }
        }

        // Services issus de la matrice (questions structurelles organigramme)
        // — on ne les ajoute pas tels quels, c'est plutot l'organigramme la source.

        // Services deja attribues sur le pivot
        $polesAttribues = DB::table('client_user')
            ->join('users', 'users.id', '=', 'client_user.user_id')
            ->where('client_user.client_id', $client->id)
            ->whereNotNull('client_user.service')
            ->whereNull('users.deleted_at')
            ->select('client_user.pole', 'client_user.service', 'users.id as user_id', 'users.prenom', 'users.nom')
            ->get();

        // Fusion par cle (pole + service)
        $map = [];
        foreach ($sources as $row) {
            $cle = mb_strtolower($row['pole'] . '||' . $row['service']);
            if (! isset($map[$cle])) {
                $map[$cle] = [
                    'pole' => $row['pole'],
                    'service' => $row['service'],
                    'libelle' => $row['pole'] . ' / ' . $row['service'],
                    'user_id' => null,
                    'user_libelle' => null,
                ];
            }
        }
        foreach ($polesAttribues as $row) {
            $cle = mb_strtolower($row->pole . '||' . $row->service);
            if (! isset($map[$cle])) {
                $map[$cle] = [
                    'pole' => $row->pole,
                    'service' => $row->service,
                    'libelle' => $row->pole . ' / ' . $row->service,
                    'user_id' => null,
                    'user_libelle' => null,
                ];
            }
            $map[$cle]['user_id'] = $row->user_id;
            $map[$cle]['user_libelle'] = trim($row->prenom . ' ' . $row->nom);
        }

        $services = collect(array_values($map))->sortBy('libelle')->values();

        return response()->json(['data' => $services]);
    }

    /**
     * Liste TOUS les poles connus de l'application pour le client_admin connecte.
     *
     * Sources fusionnees (case-insensitive sur le nom) :
     *   1. Catalogue standard : MatriceTemplate::defaut() (5 poles de reference)
     *   2. Matrices de collecte des missions du client (poles standards + custom)
     *   3. Organigrammes des missions du client (structure.*.pole)
     *   4. Pivot client_user.pole (poles deja attribues — meme si plus dans
     *      l'organigramme, pour ne pas perdre un legacy)
     *
     * Chaque pole est retourne avec son occupation eventuelle (user_id +
     * libelle) pour que le front puisse desactiver les choix deja pris.
     */
    public function poles(Request $request): JsonResponse
    {
        $client = $this->clientCourant($request);
        if (! $client) {
            return response()->json(['data' => []]);
        }

        $sources = collect();

        // 1. Catalogue standard de l'application
        foreach (MatriceTemplate::defaut() as $bloc) {
            $sources->push((string) ($bloc['pole'] ?? ''));
        }

        // 2 + 3. Missions du client : matrice + organigramme
        $missions = $client->missions()->with(['organigramme', 'matriceCollecte'])->get();
        foreach ($missions as $mission) {
            foreach (($mission->matriceCollecte?->reponses ?? []) as $bloc) {
                $sources->push((string) ($bloc['pole'] ?? ''));
            }
            foreach (($mission->organigramme?->structure ?? []) as $bloc) {
                $sources->push((string) ($bloc['pole'] ?? ''));
            }
        }

        // 4. Poles deja attribues sur le pivot
        $polesAttribues = DB::table('client_user')
            ->join('users', 'users.id', '=', 'client_user.user_id')
            ->where('client_user.client_id', $client->id)
            ->whereNotNull('client_user.pole')
            ->whereNull('users.deleted_at')
            ->select('client_user.pole', 'users.id as user_id', 'users.prenom', 'users.nom')
            ->get();

        // Fusion case-insensitive : un seul exemplaire par nom
        $map = [];
        foreach ($sources as $nom) {
            $nom = trim($nom);
            if ($nom === '') continue;
            $key = mb_strtolower($nom);
            if (! isset($map[$key])) {
                $map[$key] = ['nom' => $nom, 'user_id' => null, 'user_libelle' => null];
            }
        }
        foreach ($polesAttribues as $row) {
            $nom = trim($row->pole);
            if ($nom === '') continue;
            $key = mb_strtolower($nom);
            if (! isset($map[$key])) {
                $map[$key] = ['nom' => $nom, 'user_id' => null, 'user_libelle' => null];
            }
            $map[$key]['user_id'] = $row->user_id;
            $map[$key]['user_libelle'] = trim($row->prenom . ' ' . $row->nom);
        }

        $poles = collect(array_values($map))->sortBy('nom')->values();

        return response()->json(['data' => $poles]);
    }

    /**
     * Liste les utilisateurs de l'entreprise du client_admin connecte.
     */
    public function index(Request $request): JsonResponse
    {
        $client = $this->clientCourant($request);
        if (! $client) {
            return response()->json(['data' => []]);
        }

        $users = $client->utilisateurs()
            ->with('role:id,name')
            ->orderBy('users.created_at', 'desc')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'nom' => $u->nom,
                'prenom' => $u->prenom,
                'email' => $u->email,
                'telephone' => $u->telephone,
                'poste' => $u->poste,
                'role' => $u->role?->name,
                'pole' => $u->pivot->pole ?? null,
                'service' => $u->pivot->service ?? null,
                'is_active' => $u->is_active,
                'compte_valide' => $u->compte_valide,
                'must_change_password' => $u->must_change_password,
                'derniere_connexion' => $u->derniere_connexion,
                'created_at' => $u->created_at,
            ]);

        return response()->json(['data' => $users]);
    }

    /**
     * Cree un nouvel utilisateur pour l'entreprise du client_admin.
     * Un mot de passe temporaire est genere et envoye par email a l'utilisateur.
     */
    public function store(Request $request): JsonResponse
    {
        $client = $this->clientCourant($request);
        if (! $client) {
            return response()->json([
                'message' => 'Aucune entreprise rattachee a votre compte.',
            ], 422);
        }

        $data = $request->validate([
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->whereNull('deleted_at'),
            ],
            'telephone' => 'nullable|string|max:30',
            'poste' => 'nullable|string|max:255',
            'pole' => 'required|string|max:255',
            // Service optionnel : si fourni, l'utilisateur est scope a ce
            // (pole, service) precis. Sinon, il supervise tout le pole.
            'service' => 'nullable|string|max:255',
            'role' => 'nullable|in:client,client_admin',
        ], [
            'email.unique' => 'Cette adresse e-mail est deja utilisee.',
        ]);

        // Unicite metier (gere par l'index composite DB sinon, mais on fait un
        // pre-check pour retourner un message clair plutot qu'une 500 SQL).
        $polePivot = $data['pole'];
        $servicePivot = $data['service'] ?? null;
        $this->verifierUniciteRattachement($client, $polePivot, $servicePivot, null);

        $roleName = $data['role'] ?? 'client';
        $role = Role::where('name', $roleName)->first();
        if (! $role) {
            return response()->json(['message' => "Role $roleName introuvable."], 500);
        }

        $motDePasseTemp = GenerateurMotDePasse::temporaire(16);

        try {
            $user = DB::transaction(function () use ($data, $role, $client, $motDePasseTemp) {
                $user = User::create([
                    'nom' => $data['nom'],
                    'prenom' => $data['prenom'],
                    'email' => $data['email'],
                    'password' => Hash::make($motDePasseTemp),
                    'telephone' => $data['telephone'] ?? null,
                    'poste' => $data['poste'] ?? null,
                    'is_active' => true,
                    'compte_valide' => true, // Compte cree par client_admin = deja valide
                    'must_change_password' => true,
                    'mdp_temporaire_expire_le' => now()->addDays(7),
                    'role_id' => $role->id,
                ]);

                // Attachement au client AVEC le pole/service sur le pivot
                $client->utilisateurs()->attach($user->id, [
                    'pole' => $data['pole'],
                    'service' => $data['service'] ?? null,
                ]);

                return $user;
            });
        } catch (\Throwable $e) {
            Log::error("Echec creation user client_admin : {$e->getMessage()}");

            return response()->json([
                'message' => "Erreur lors de la creation : {$e->getMessage()}",
            ], 500);
        }

        // Envoi de l'email avec credentials. Echec silencieux pour ne pas bloquer
        // la creation (l'admin peut renvoyer un reset password si besoin).
        try {
            Mail::to($user->email)->send(new NewUserCredentialsMail(
                user: $user,
                motDePasseTemporaire: $motDePasseTemp,
                nomEntreprise: $client->raison_sociale,
                createPar: $request->user()->prenom . ' ' . $request->user()->nom,
            ));
        } catch (\Throwable $e) {
            Log::warning("Email credentials non envoye pour user {$user->id}", ['error' => $e->getMessage()]);
        }

        $this->audit->log('client.user_cree', [
            'user_id' => $user->id,
            'client_id' => $client->id,
            'pole' => $data['pole'],
            'service' => $data['service'] ?? null,
            'cree_par' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Utilisateur cree. Un e-mail contenant ses identifiants temporaires lui a ete envoye.',
            'user' => [
                'id' => $user->id,
                'nom' => $user->nom,
                'prenom' => $user->prenom,
                'email' => $user->email,
                'pole' => $data['pole'],
                'service' => $data['service'] ?? null,
            ],
        ], 201);
    }

    /**
     * Verifie qu'aucun autre utilisateur du meme client n'occupe deja le couple
     * (pole, service). `$ignoreUserId` est pris en compte pour permettre une
     * mise a jour sans s'auto-bloquer.
     */
    private function verifierUniciteRattachement(Client $client, string $pole, ?string $service, ?int $ignoreUserId): void
    {
        $q = DB::table('client_user')
            ->where('client_id', $client->id)
            ->where('pole', $pole);
        if ($service === null || $service === '') {
            $q->whereNull('service');
        } else {
            $q->where('service', $service);
        }
        if ($ignoreUserId) {
            $q->where('user_id', '!=', $ignoreUserId);
        }
        if ($q->exists()) {
            $label = $pole . ($service ? " / $service" : ' (pole entier)');
            abort(response()->json([
                'message' => "Le rattachement \"$label\" est deja attribue a un autre utilisateur de votre entreprise.",
                'errors' => ['pole' => ["Le rattachement \"$label\" est deja attribue a un autre utilisateur."]],
            ], 422));
        }
    }

    /**
     * Modifie les informations d'un utilisateur de l'entreprise (hors mot de passe).
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $client = $this->verifierProprietaire($request, $user);

        $data = $request->validate([
            'nom' => 'sometimes|string|max:255',
            'prenom' => 'sometimes|string|max:255',
            'telephone' => 'nullable|string|max:30',
            'poste' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
            'pole' => 'sometimes|string|max:255',
            'service' => 'nullable|string|max:255',
        ]);

        $polePivot = array_key_exists('pole', $data) ? $data['pole'] : null;
        $servicePivot = array_key_exists('service', $data) ? ($data['service'] ?? null) : '__NO_CHANGE__';
        unset($data['pole'], $data['service']);

        if (! empty($data)) {
            $user->update($data);
        }

        // Si on met a jour le rattachement (pole et/ou service), on verifie
        // l'unicite metier sur le nouveau couple en s'ignorant soi-meme.
        if ($polePivot !== null || $servicePivot !== '__NO_CHANGE__') {
            $currentPivot = $client->utilisateurs()->where('users.id', $user->id)->first()?->pivot;
            $newPole = $polePivot ?? $currentPivot?->pole;
            $newService = $servicePivot === '__NO_CHANGE__' ? $currentPivot?->service : $servicePivot;
            if (! $newPole) {
                abort(response()->json(['message' => 'Pole obligatoire.', 'errors' => ['pole' => ['Le pole est obligatoire.']]], 422));
            }
            $this->verifierUniciteRattachement($client, $newPole, $newService, $user->id);
            $client->utilisateurs()->updateExistingPivot($user->id, [
                'pole' => $newPole,
                'service' => $newService,
            ]);
        }

        $this->audit->log('client.user_modifie', [
            'user_id' => $user->id,
            'client_id' => $client->id,
            'modifie_par' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Utilisateur mis a jour.', 'user' => $user->fresh()]);
    }

    /**
     * Reinitialise le mot de passe d'un utilisateur : genere un nouveau mdp
     * temporaire et envoie un email. must_change_password est remis a true.
     */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $client = $this->verifierProprietaire($request, $user);

        $motDePasseTemp = GenerateurMotDePasse::temporaire(16);

        $user->update([
            'password' => Hash::make($motDePasseTemp),
            'must_change_password' => true,
            'mdp_temporaire_expire_le' => now()->addDays(7),
        ]);

        try {
            Mail::to($user->email)->send(new NewUserCredentialsMail(
                user: $user,
                motDePasseTemporaire: $motDePasseTemp,
                nomEntreprise: $client->raison_sociale,
                createPar: $request->user()->prenom . ' ' . $request->user()->nom,
            ));
        } catch (\Throwable $e) {
            Log::warning("Email reset password non envoye pour user {$user->id}", ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Mot de passe reinitialise mais l\'envoi de l\'email a echoue. Veuillez communiquer le nouveau mot de passe manuellement.',
                'mot_de_passe_temporaire' => $motDePasseTemp,
            ]);
        }

        $this->audit->log('client.user_reset_password', [
            'user_id' => $user->id,
            'client_id' => $client->id,
            'reset_par' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Mot de passe reinitialise. Un email a ete envoye a l\'utilisateur.',
        ]);
    }

    /**
     * Soft-delete un utilisateur de l'entreprise.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $client = $this->verifierProprietaire($request, $user);

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Vous ne pouvez pas supprimer votre propre compte.'], 422);
        }

        $client->utilisateurs()->detach($user->id);
        $user->delete();

        $this->audit->log('client.user_supprime', [
            'user_id' => $user->id,
            'client_id' => $client->id,
            'supprime_par' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Utilisateur supprime.']);
    }

    /**
     * Recupere l'entreprise rattachee a l'utilisateur connecte.
     */
    private function clientCourant(Request $request): ?Client
    {
        $clientId = $request->user()->clients()->value('clients.id');

        return $clientId ? Client::find($clientId) : null;
    }

    /**
     * Verifie que le user cible appartient bien a l'entreprise du client_admin connecte.
     * Sinon, 403. Retourne le Client si OK.
     */
    private function verifierProprietaire(Request $request, User $user): Client
    {
        $client = $this->clientCourant($request);
        if (! $client) {
            abort(422, 'Aucune entreprise rattachee a votre compte.');
        }

        $appartient = $client->utilisateurs()->where('users.id', $user->id)->exists();
        if (! $appartient) {
            abort(403, 'Cet utilisateur n\'appartient pas a votre entreprise.');
        }

        return $client;
    }
}
