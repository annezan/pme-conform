<?php

/**
 * Ajoute une colonne JSON `metadata` sur organigrammes pour stocker des
 * informations transversales (progression de la generation des
 * questionnaires, traces de debug, parametres de configuration, etc.).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organigrammes', function (Blueprint $table) {
            $table->jsonb('metadata')->nullable()->after('valide_at');
        });
    }

    public function down(): void
    {
        Schema::table('organigrammes', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }
};
