<?php

/**
 * Seeder TestUsersSeeder — Cree un utilisateur de test pour chaque role
 * de la plateforme, avec des emails fictifs (domaine asc-ia.local) faciles
 * a memoriser pour la recette et le support.
 *
 * Idempotent : firstOrCreate + update selectif — peut se rejouer sans
 * generer de doublons ni ecraser les mots de passe si l'admin les a changes.
 *
 * Mots de passe commun : Test@2026!
 * Rattachement : client_admin et client sont rattaches a SUNU ASSURANCE
 *                (client #1) pour que les tests soient realistes.
 *
 * Usage :
 *   php artisan db:seed --class=TestUsersSeeder
 */

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestUsersSeeder extends Seeder
{
    private const PASSWORD_TEST = 'Test@2026!';

    public function run(): void
    {
        // Client de rattachement pour les comptes clients de test.
        // 1) SUNU ASSURANCE si present (client de reference en dev)
        // 2) Sinon le premier client existant
        // 3) Sinon on cree un client de demo dedie aux tests
        //    (garantit que le seeder marche sur un environnement vierge)
        $client = Client::where('raison_sociale', 'SUNU ASSURANCE')->first()
            ?? Client::orderBy('id')->first();

        if (! $client) {
            $client = Client::create([
                'raison_sociale' => 'CLIENT DEMO (Tests)',
                'sigle' => 'DEMO',
                'description_activite' => 'Entreprise fictive utilisee uniquement pour les tests et la formation. A supprimer avant mise en service reelle.',
                'pays' => 'Cote d\'Ivoire',
                'ville' => 'Abidjan',
                'adresse' => 'Cocody, Abidjan',
                'telephone' => '+225 00 00 00 00 00',
                'email' => 'demo@asc-ia.local',
                'contact_principal_nom' => 'Contact Demo',
                'contact_principal_email' => 'demo@asc-ia.local',
                'type_structure' => 'sarl',
                'effectif' => 10,
                'statut' => 'actif',
            ]);
            $this->command?->info("  → Client de demo cree : CLIENT DEMO (Tests) (id={$client->id})");
        }

        $comptes = [
            [
                'role' => 'manager',
                'email' => 'manager@asc-ia.local',
                'prenom' => 'Marie',
                'nom' => 'Manager',
                'client' => null,
            ],
            [
                'role' => 'consultant',
                'email' => 'consultant@asc-ia.local',
                'prenom' => 'Jean',
                'nom' => 'Consultant',
                'client' => $client, // rattache au client pour le voir dans son portefeuille
            ],
            [
                'role' => 'client_admin',
                'email' => 'client-admin@asc-ia.local',
                'prenom' => 'Paul',
                'nom' => 'Directeur',
                'client' => $client,
                'pole' => 'Direction',
            ],
            [
                'role' => 'client',
                'email' => 'client@asc-ia.local',
                'prenom' => 'Sophie',
                'nom' => 'Employée',
                'client' => $client,
                'pole' => 'Marketing',
            ],
        ];

        foreach ($comptes as $conf) {
            $role = Role::where('name', $conf['role'])->first();
            if (! $role) {
                $this->command?->warn("Role {$conf['role']} introuvable, compte ignore.");
                continue;
            }

            $user = User::firstOrCreate(
                ['email' => $conf['email']],
                [
                    'nom' => $conf['nom'],
                    'prenom' => $conf['prenom'],
                    'password' => Hash::make(self::PASSWORD_TEST),
                    'role_id' => $role->id,
                    'is_active' => true,
                    'compte_valide' => true,
                    'valide_le' => now(),
                    'must_change_password' => false,
                    'email_verified_at' => now(),
                ]
            );

            // Meme si l'user existait deja, on garantit qu'il est actif et valide
            // (utile si le seeder est rejoue apres une modification manuelle).
            if (! $user->wasRecentlyCreated) {
                $user->update([
                    'role_id' => $role->id, // au cas ou le role aurait change
                    'is_active' => true,
                    'compte_valide' => true,
                    'valide_le' => $user->valide_le ?? now(),
                    'must_change_password' => false,
                ]);
            }

            // Rattachement au client si defini
            if (! empty($conf['client'])) {
                $conf['client']->utilisateurs()->syncWithoutDetaching([
                    $user->id => [
                        'pole' => $conf['pole'] ?? null,
                        'service' => null,
                    ],
                ]);
            }

            $etat = $user->wasRecentlyCreated ? 'CREE' : 'DEJA PRESENT (verifie)';
            $rattach = ! empty($conf['client']) ? " → rattache a {$conf['client']->raison_sociale}" : '';
            $this->command?->info("  [{$conf['role']}] {$conf['email']} : {$etat}{$rattach}");
        }

        $this->command?->info("\nMot de passe pour tous les comptes de test : " . self::PASSWORD_TEST);
    }
}
