<?php

/**
 * Ajoute une colonne `risque` a `ecarts` pour stocker l'analyse du risque
 * lie a l'ecart (distinct du constat et de la recommandation).
 *
 * Permet d'afficher 3 sections distinctes dans le rapport :
 *   - Constat (description_ecart) : ce qui manque / est insuffisant
 *   - Risque (risque)             : ce que la non-conformite expose
 *   - Recommandation              : action concrete a mener
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecarts', function (Blueprint $table) {
            $table->text('risque')->nullable()->after('description_ecart');
        });
    }

    public function down(): void
    {
        Schema::table('ecarts', function (Blueprint $table) {
            $table->dropColumn('risque');
        });
    }
};
