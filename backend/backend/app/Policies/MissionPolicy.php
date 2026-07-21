<?php

/**
 * Policy MissionPolicy — Regles d'acces aux missions.
 *
 * Regles fonctionnelles (definies dans le besoin) :
 *
 *   Consultant :
 *     - Voit uniquement les missions ou il est affecte (pivot mission_user)
 *       OU dont il est le createur / responsable_id.
 *     - Peut creer une nouvelle mission (il devient automatiquement createur).
 *     - Peut ajouter/retirer d'autres consultants sur les missions qu'il a
 *       creees (created_by = son id).
 *
 *   Manager / Administrateur ASC :
 *     - Peuvent voir toutes les missions de la plateforme.
 *     - Peuvent modifier les affectations sur n'importe quelle mission.
 *     - Peuvent retirer un consultant meme s'ils ne sont pas createurs.
 *
 * Convention permission :
 *   - view-missions        : autorise a acceder au module missions
 *   - view-all-missions      : bypass scope (voit toutes les missions,
 *                            peut modifier n'importe laquelle)
 *   - create-missions      : peut creer
 *   - update-missions      : peut modifier une mission
 *   - delete-missions      : peut supprimer une mission
 */

namespace App\Policies;

use App\Models\Mission;
use App\Models\User;

class MissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view-missions');
    }

    public function view(User $user, Mission $mission): bool
    {
        if (! $user->hasPermissionTo('view-missions')) {
            return false;
        }

        // Manager / Admin : voit tout.
        if ($user->hasPermissionTo('view-all-missions')) {
            return true;
        }

        return $this->estAffecteOuCreateur($user, $mission);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create-missions');
    }

    public function update(User $user, Mission $mission): bool
    {
        if (! $user->hasPermissionTo('update-missions')) {
            return false;
        }
        if ($user->hasPermissionTo('view-all-missions')) {
            return true;
        }
        return $this->estAffecteOuCreateur($user, $mission);
    }

    public function delete(User $user, Mission $mission): bool
    {
        if (! $user->hasPermissionTo('delete-missions')) {
            return false;
        }
        // Seul createur, manager ou admin peuvent supprimer une mission.
        return $user->hasPermissionTo('view-all-missions') || $mission->created_by === $user->id;
    }

    /**
     * Attacher un consultant a la mission.
     * Autorise : createur, manager, admin.
     */
    public function attacherConsultants(User $user, Mission $mission): bool
    {
        if ($user->hasPermissionTo('view-all-missions')) {
            return true;
        }
        return $mission->created_by === $user->id;
    }

    /**
     * Retirer un consultant de la mission.
     * Meme regle que attacher, sauf qu'on ne peut jamais retirer le
     * createur (protection contre la mission orpheline).
     */
    public function detacherConsultant(User $user, Mission $mission, User $cible): bool
    {
        if ($cible->id === $mission->created_by) {
            return false; // pas de retrait du createur
        }
        if ($user->hasPermissionTo('view-all-missions')) {
            return true;
        }
        return $mission->created_by === $user->id;
    }

    /**
     * L'user est-il affecte (pivot) ou createur/responsable historique ?
     */
    private function estAffecteOuCreateur(User $user, Mission $mission): bool
    {
        if ($mission->created_by === $user->id || $mission->responsable_id === $user->id) {
            return true;
        }
        return $mission->consultants()->where('users.id', $user->id)->exists();
    }
}
