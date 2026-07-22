<?php

/**
 * Contrôleur AuthController — API d'authentification.
 *
 * Gère la connexion, la déconnexion et la vérification de session.
 * Utilise Sanctum en mode SPA (cookie-based auth).
 * Enregistre chaque tentative dans l'audit trail.
 */

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function __construct(
        private AuditService $auditService,
    ) {}

    /**
     * Connecte l'utilisateur (POST /api/login).
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            $this->auditService->connexion(0, 'echec');

            return response()->json([
                'message' => 'Les identifiants fournis sont incorrects.',
            ], 422);
        }

        $user = Auth::user();

        // Compte en attente de validation par AS Consulting (inscription publique).
        if (! $user->compte_valide) {
            Auth::logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
            }

            return response()->json([
                'message' => 'Votre compte est en attente de validation par AS Consulting. Vous recevrez un e-mail dès qu\'il sera validé.',
                'code' => 'COMPTE_NON_VALIDE',
            ], 403);
        }

        if (! $user->is_active) {
            Auth::logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
            }

            return response()->json([
                'message' => 'Votre compte a été désactivé. Contactez un administrateur.',
                'code' => 'COMPTE_DESACTIVE',
            ], 403);
        }

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }
        $user->update(['derniere_connexion' => now()]);
        $this->auditService->connexion($user->id);

        // Créer un token pour l'API
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $this->formatUser($user),
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => config('sanctum.expiration', 525600) * 60, // en secondes
            'message' => 'Connexion réussie.',
        ]);
    }

    /**
     * Inscription publique d'un nouveau client + son compte utilisateur principal
     * (POST /api/register). Le user est rattache au client via la pivot client_user
     * et recoit le role "client_admin". Apres creation, l'utilisateur est
     * automatiquement connecte et reçoit un token Sanctum.
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            // Entreprise
            'client' => ['required', 'array'],
            'client.raison_sociale' => [
                'required', 'string', 'max:255',
                // Interdit un nom d'entreprise deja utilise par un client actif,
                // insensible a la casse et aux espaces ("yango" == " Yango ").
                // Client::query() exclut deja les clients soft-deleted, donc un
                // nom se libere si l'entreprise a ete supprimee.
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $existe = Client::whereRaw('LOWER(TRIM(raison_sociale)) = ?', [
                        mb_strtolower(trim((string) $value)),
                    ])->exists();

                    if ($existe) {
                        $fail('Une entreprise avec ce nom existe déjà.');
                    }
                },
            ],
            'client.pays' => ['required', 'string', 'max:100'],
            'client.ville' => ['nullable', 'string', 'max:100'],
            'client.adresse' => ['nullable', 'string', 'max:500'],
            'client.telephone' => ['nullable', 'string', 'max:50'],
            'client.email' => ['nullable', 'email', 'max:255'],
            // Contact principal = identite de l'utilisateur a creer (User nom/prenom/email).
            // Required cote register, optionnels cote /clients (CRUD agent).
            'client.contact_principal_nom' => ['required', 'string', 'max:255'],
            'client.contact_principal_email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->whereNull('deleted_at'),
            ],
            'client.secteurs_activite_ids' => ['required', 'array', 'min:1'],
            'client.secteurs_activite_ids.*' => ['integer', 'exists:secteurs_activite,id'],

            // Compte utilisateur : seuls les mots de passe sont saisis explicitement,
            // le reste est derive du contact principal de l'entreprise.
            'user' => ['required', 'array'],
            'user.password' => ['required', 'string', 'min:8', 'confirmed'],
            'user.password_confirmation' => ['required', 'string'],
        ]);

        $clientAdminRole = Role::where('name', 'client_admin')->first();
        if (! $clientAdminRole) {
            return response()->json([
                'message' => 'Configuration manquante : le role "client_admin" est introuvable.',
            ], 500);
        }

        // Le contact principal de l'entreprise sert d'identite a l'utilisateur.
        // On split "Prenom Nom" sur le premier espace ; s'il n'y en a pas,
        // on remplit nom = prenom = la valeur saisie.
        $contactComplet = trim($data['client']['contact_principal_nom']);
        $parts = preg_split('/\s+/', $contactComplet, 2);
        $userPrenom = $parts[0] ?? $contactComplet;
        $userNom = $parts[1] ?? $parts[0] ?? $contactComplet;
        $userEmail = $data['client']['contact_principal_email'];
        $userTelephone = $data['client']['telephone'] ?? null;

        try {
            [$user, $client] = DB::transaction(function () use ($data, $clientAdminRole, $userPrenom, $userNom, $userEmail, $userTelephone) {
                $client = Client::create([
                    'raison_sociale' => $data['client']['raison_sociale'],
                    'pays' => $data['client']['pays'],
                    'ville' => $data['client']['ville'] ?? null,
                    'adresse' => $data['client']['adresse'] ?? null,
                    'telephone' => $data['client']['telephone'] ?? null,
                    'email' => $data['client']['email'] ?? null,
                    'statut' => 'prospect',
                    'contact_principal_nom' => $data['client']['contact_principal_nom'],
                    'contact_principal_email' => $data['client']['contact_principal_email'],
                    'contact_principal_telephone' => $userTelephone,
                ]);

                if (! empty($data['client']['secteurs_activite_ids'])) {
                    $client->synchroniserSecteurs($data['client']['secteurs_activite_ids']);
                }

                $user = User::create([
                    'nom' => $userNom,
                    'prenom' => $userPrenom,
                    'email' => $userEmail,
                    'password' => Hash::make($data['user']['password']),
                    'telephone' => $userTelephone,
                    // Compte cree en attente : ne peut se connecter qu'apres validation
                    // par ASC via /admin/comptes-en-attente.
                    'is_active' => true,
                    'compte_valide' => false,
                    'role_id' => $clientAdminRole->id,
                ]);

                $client->utilisateurs()->attach($user->id);

                return [$user, $client];
            });
        } catch (\Throwable $e) {
            // Trace complete cote serveur (utile pour diagnostiquer les
            // erreurs de creation compte : DB, secteurs, contraintes...).
            \Illuminate\Support\Facades\Log::error('Erreur inscription publique', [
                'error' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'email' => $userEmail,
                'raison_sociale' => $data['client']['raison_sociale'] ?? null,
            ]);

            return response()->json([
                'message' => 'Erreur lors de la creation du compte.',
                'detail' => $e->getMessage(),
            ], 500);
        }

        // Pas de connexion immediate : le user doit attendre la validation ASC.
        // On renvoie un message clair indiquant que le compte est en attente.
        return response()->json([
            'message' => 'Votre compte a été créé avec succès. Il est en attente de validation par AS Consulting. Vous recevrez un e-mail dès qu\'il sera validé.',
            'compte_en_attente' => true,
            'client' => $client->only(['id', 'raison_sociale', 'statut']),
            'email_contact' => $userEmail,
        ], 201);
    }

    /**
     * Déconnecte l'utilisateur (POST /api/logout).
     */
    public function logout(Request $request): JsonResponse
    {
        $this->auditService->deconnexion();

        // Deconnecter via le guard web (Sanctum SPA utilise les sessions web)
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Déconnexion réussie.']);
    }

    /**
     * Retourne l'utilisateur connecté (GET /api/user).
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        return response()->json([
            'user' => $this->formatUser($user),
        ]);
    }

    private function formatUser($user): array
    {
        return [
            'id' => $user->id,
            'nom' => $user->nom,
            'prenom' => $user->prenom,
            'nom_complet' => $user->nom_complet,
            'email' => $user->email,
            'telephone' => $user->telephone,
            'poste' => $user->poste,
            'role' => $user->role ? $user->role->name : null,
            'permissions' => $this->permissionsEtendues($user),
            'must_change_password' => (bool) $user->must_change_password,
            'mdp_temporaire_expire_le' => $user->mdp_temporaire_expire_le,
            'created_at' => $user->created_at,
        ];
    }

    /**
     * Retourne les permissions du role en developpant les umbrellas `manage-*`
     * en leurs equivalents granulaires (view/create/update/delete/...).
     *
     * Le seeder n'attribue que `manage-foo` au role admin, mais le front teste
     * `view-foo`, `create-foo`, etc. ; sans cette expansion, l'admin ne voit
     * pas les boutons d'action malgre son acces total cote API.
     */
    private function permissionsEtendues($user): array
    {
        if (! $user->role) {
            return [];
        }

        $brutes = $user->role->permissions->pluck('name')->all();

        // Admin = super-user (intent declare du seeder « toutes les permissions »).
        // On expose un marqueur special `*` que le front peut traiter en bypass.
        if ($user->role->name === 'admin') {
            return array_values(array_unique(array_merge($brutes, ['*'])));
        }

        $verbes = ['view', 'create', 'update', 'delete', 'accept', 'close', 'sign',
            'revoke', 'download', 'generate', 'regenerate', 'upload', 'input',
            'validate', 'archive', 'submit', 'freeze', 'reindex', 'enrich',
            'cancel', 'restart'];

        $etendues = $brutes;
        foreach ($brutes as $name) {
            if (str_starts_with($name, 'manage-')) {
                $ressource = substr($name, strlen('manage-'));
                foreach ($verbes as $verbe) {
                    $etendues[] = "{$verbe}-{$ressource}";
                }
            }
        }

        return array_values(array_unique($etendues));
    }
}
