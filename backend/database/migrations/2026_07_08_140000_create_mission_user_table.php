<?php

/**
 * Cree la table pivot mission_user pour rattacher plusieurs consultants
 * (ou managers, admins) a une meme mission.
 *
 * Avant cette migration, chaque mission avait UN seul responsable via la
 * colonne responsable_id. On garde cette colonne pour la compat (elle sert
 * de "responsable principal") mais on ajoute cette pivot pour permettre
 * l'affectation de plusieurs utilisateurs.
 *
 * Backfill : chaque mission existante recoit une ligne dans la pivot avec
 * son responsable_id historique, pour que les scopes existants continuent
 * de fonctionner sans regression.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mission_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Role dans la mission (indicatif). Ne remplace pas le role Spatie
            // de l'user. Sert a distinguer visuellement les affectations.
            $table->string('role_dans_mission', 30)->default('consultant');
            $table->timestamp('affecte_le')->useCurrent();
            $table->foreignId('affecte_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['mission_id', 'user_id']);
            $table->index('user_id');
        });

        // Backfill : chaque mission historique a une ligne pivot avec son
        // responsable_id. On garde createur/responsable dans la pivot pour
        // que le scoping "voir les missions affectees" les trouve aussi.
        $missions = DB::table('missions')
            ->whereNull('deleted_at')
            ->whereNotNull('responsable_id')
            ->get(['id', 'responsable_id', 'created_by', 'created_at']);

        foreach ($missions as $m) {
            // Ligne pour le responsable
            DB::table('mission_user')->insertOrIgnore([
                'mission_id' => $m->id,
                'user_id' => $m->responsable_id,
                'role_dans_mission' => 'responsable',
                'affecte_le' => $m->created_at,
                'affecte_par' => $m->created_by,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Ligne pour le createur si different
            if ($m->created_by && $m->created_by !== $m->responsable_id) {
                DB::table('mission_user')->insertOrIgnore([
                    'mission_id' => $m->id,
                    'user_id' => $m->created_by,
                    'role_dans_mission' => 'createur',
                    'affecte_le' => $m->created_at,
                    'affecte_par' => $m->created_by,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_user');
    }
};
