<?php

/**
 * Lot — Sources d'analyse : ajoute la colonne questionnaires_ids
 * pour permettre de croiser les reponses des formulaires renseignes
 * (par le client ou un agent AS Consulting) avec les referentiels.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analyses', function (Blueprint $table) {
            $table->json('questionnaires_ids')->nullable()->after('documents_ids');
        });
    }

    public function down(): void
    {
        Schema::table('analyses', function (Blueprint $table) {
            $table->dropColumn('questionnaires_ids');
        });
    }
};
