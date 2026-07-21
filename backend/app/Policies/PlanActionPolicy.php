<?php

/**
 * Policy PlanActionPolicy — Regles d'acces aux plans d'action.
 *
 * 100% basee sur les permissions. Les noms de roles ne sont JAMAIS verifies
 * directement : l'admin peut creer des roles dynamiques via /admin/roles et
 * leur affecter ces permissions, ce qui suffit a debloquer les actions.
 *
 * Convention :
 *   - manage-* (admin/manager) = bypass scope (voit tout)
 *   - sinon : scope aux clients assignes a l'utilisateur
 */

namespace App\Policies;

use App\Models\PlanAction;
use App\Models\User;

class PlanActionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view-plans-actions');
    }

    public function view(User $user, PlanAction $plan): bool
    {
        if (! $user->hasPermissionTo('view-plans-actions')) {
            return false;
        }

        // view-all-plans-actions = bypass scope (admin/manager).
        if ($user->hasPermissionTo('view-all-plans-actions')) {
            return true;
        }

        // Sinon, l'utilisateur ne voit que les plans de ses clients assignes.
        return $user->clients()->where('clients.id', $plan->client_id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create-plans-actions');
    }

    public function update(User $user, PlanAction $plan): bool
    {
        if ($plan->statut === 'cloture') {
            return false;
        }

        if (! $user->hasPermissionTo('update-plans-actions')) {
            return false;
        }

        return $user->hasPermissionTo('view-all-plans-actions')
            || $user->clients()->where('clients.id', $plan->client_id)->exists();
    }

    public function accepter(User $user, PlanAction $plan): bool
    {
        if ($plan->statut !== 'propose') {
            return false;
        }

        return $user->hasPermissionTo('accept-plans-actions')
            && $user->clients()->where('clients.id', $plan->client_id)->exists();
    }

    public function cloturer(User $user, PlanAction $plan): bool
    {
        if (! $user->hasPermissionTo('close-plans-actions')) {
            return false;
        }

        return $user->hasPermissionTo('view-all-plans-actions')
            || $user->clients()->where('clients.id', $plan->client_id)->exists();
    }

    public function mettreAJourItem(User $user, PlanAction $plan): bool
    {
        if ($plan->statut === 'cloture') {
            return false;
        }

        if (! $user->hasPermissionTo('manage-plans-actions-items')) {
            return false;
        }

        return $user->hasPermissionTo('view-all-plans-actions')
            || $user->clients()->where('clients.id', $plan->client_id)->exists();
    }

    public function delete(User $user, PlanAction $plan): bool
    {
        if ($plan->statut === 'cloture') {
            return false;
        }

        return $user->hasPermissionTo('delete-plans-actions');
    }
}
