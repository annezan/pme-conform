<?php

/**
 * Ajoute un flag enrichissement_ia pour choisir entre :
 *  - mode rapide (defaut, false) : redaction d'ecart instantanee, pas d'appel LLM par ecart
 *  - mode enrichi (true) : LLM redige titre/description/recommandation de chaque ecart
 *
 * Le mode rapide descend l'analyse de 20+ minutes a ~30 secondes.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analyses', function (Blueprint $table) {
            $table->boolean('enrichissement_ia')->default(false)->after('etape_courante');
        });
    }

    public function down(): void
    {
        Schema::table('analyses', function (Blueprint $table) {
            $table->dropColumn('enrichissement_ia');
        });
    }
};
