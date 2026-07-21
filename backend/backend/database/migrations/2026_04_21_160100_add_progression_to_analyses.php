<?php

/**
 * Ajoute un suivi granulaire de progression a la table analyses.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analyses', function (Blueprint $table) {
            $table->unsignedInteger('nb_exigences_total')->default(0)->after('nb_exigences_verifiees');
            $table->unsignedInteger('progression_pct')->default(0)->after('nb_exigences_total');
            $table->string('etape_courante')->nullable()->after('progression_pct');
        });
    }

    public function down(): void
    {
        Schema::table('analyses', function (Blueprint $table) {
            $table->dropColumn(['nb_exigences_total', 'progression_pct', 'etape_courante']);
        });
    }
};
