<?php

/**
 * Policy TraitementPolicy — Regles d'acces aux fiches de traitement.
 *
 * 100% basee sur les permissions. Les noms de roles ne sont JAMAIS verifies
 * directement : l'admin peut creer des roles dynamiques via /admin/roles et
 * leur affecter ces permissions, ce qui suffit a debloquer les actions.
 *
 * Convention :
 *   - view-all-traitements (admin/manager) = bypass scope (voit tout)
 *   - sinon : scope aux clients assignes a l'utilisateur
 */

namespace App\Policies;

use App\Models\Traitement;
use App\Models\User;

class TraitementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view-traitements');
    }

    public function view(User $user, Traitement $traitement): bool
    {
        if (! $user->hasPermissionTo('view-traitements')) {
            return false;
        }

        if ($user->hasPermissionTo('view-all-traitements')) {
            return true;
        }

        return $user->clients()->where('clients.id', $traitement->client_id)->exists();
    }

    public function create(User $user): bool
    {
        // Cree un traitement pour une entreprise client : besoin de la permission
        // et d'avoir au moins un client rattache (sinon, aucun client_id valide).
        return $user->hasPermissionTo('create-traitements')
            && $user->clients()->exists();
    }

    public function update(User $user, Traitement $traitement): bool
    {
        if ($traitement->statut === 'archive') {
            return false;
        }

        if (! $user->hasPermissionTo('update-traitements')) {
            return false;
        }

        return $user->hasPermissionTo('view-all-traitements')
            || $user->clients()->where('clients.id', $traitement->client_id)->exists();
    }

    public function valider(User $user, Traitement $traitement): bool
    {
        if ($traitement->statut !== 'brouillon') {
            return false;
        }

        if (! $user->hasPermissionTo('validate-traitements')) {
            return false;
        }

        return $user->hasPermissionTo('view-all-traitements')
            || $user->clients()->where('clients.id', $traitement->client_id)->exists();
    }

    public function archiver(User $user, Traitement $traitement): bool
    {
        if (! $user->hasPermissionTo('archive-traitements')) {
            return false;
        }

        return $user->hasPermissionTo('view-all-traitements')
            || $user->clients()->where('clients.id', $traitement->client_id)->exists();
    }

    public function delete(User $user, Traitement $traitement): bool
    {
        if ($traitement->statut !== 'brouillon') {
            return false;
        }

        // L'auteur peut toujours supprimer son brouillon, sinon il faut la permission.
        return $traitement->saisi_par === $user->id
            || $user->hasPermissionTo('delete-traitements');
    }
}
