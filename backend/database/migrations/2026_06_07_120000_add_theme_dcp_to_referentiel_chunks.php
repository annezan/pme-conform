<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute la colonne theme_dcp sur referentiel_chunks.
 *
 * Le theme (securite, registre, dpo, consentement, conservation, droits,
 * transfert, sous_traitance, aipd, information, notification_violation,
 * responsable_traitement, formalites_artci, principes, mineurs, marketing,
 * donnees_sensibles, portabilite, autre) est calcule UNE FOIS par le LLM
 * au moment de l'indexation du referentiel, puis lu sans cout par le
 * GapAnalysisService a chaque analyse.
 *
 * Remplace l'heuristique fragile par mots-cles (cf. detecterThemeDcp).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referentiel_chunks', function (Blueprint $table) {
            $table->string('theme_dcp', 40)->nullable()->after('categorie_exigence')->index();
        });
    }

    public function down(): void
    {
        Schema::table('referentiel_chunks', function (Blueprint $table) {
            $table->dropIndex(['theme_dcp']);
            $table->dropColumn('theme_dcp');
        });
    }
};
