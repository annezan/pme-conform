<?php

/**
 * Nettoyage : supprime definitivement les missions "Boite de reception"
 * (artefacts de l'ancien systeme d'upload) maintenant que les documents
 * sont rattaches directement a leur client via documents.client_id.
 *
 * On remet a NULL le mission_id des documents qui pointaient encore sur
 * une Boite de reception, puis on detruit ces missions.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $boiteIds = DB::table('missions')
            ->where('titre', 'like', 'Boite de reception%')
            ->pluck('id');

        if ($boiteIds->isEmpty()) {
            return;
        }

        // Documents qui pointaient sur une Boite de reception : on detache le mission_id.
        DB::table('documents')
            ->whereIn('mission_id', $boiteIds)
            ->update(['mission_id' => null]);

        // Suppression definitive (force-delete) des missions Boite de reception,
        // y compris celles qui etaient soft-deleted.
        DB::table('missions')->whereIn('id', $boiteIds)->delete();
    }

    public function down(): void
    {
        // Pas de rollback : la suppression est volontaire et definitive.
    }
};
