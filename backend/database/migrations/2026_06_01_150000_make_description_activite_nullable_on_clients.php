<?php

/**
 * Rend la colonne `description_activite` nullable sur la table `clients`.
 *
 * Justification : lors d'une inscription publique via /inscription, l'entreprise
 * n'a pas a fournir une description detaillee de son activite (les secteurs
 * d'activite par pivot suffisent a categoriser). Cette colonne etait NOT NULL
 * sans valeur par defaut, ce qui faisait planter le POST /register en 500.
 *
 * Les utilisateurs internes (admin/manager) peuvent toujours la remplir via
 * /admin/clients pour enrichir le profil client.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->text('description_activite')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->text('description_activite')->nullable(false)->change();
        });
    }
};
