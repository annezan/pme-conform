<?php

/**
 * Ajoute des champs a ecarts pour identifier precisement :
 *  - le document source (nom du fichier client)
 *  - le numero de question (si le chunk vient d'un questionnaire)
 *  - le texte de la question d'origine
 *
 * Ces champs sont remplis par GapAnalysisService quand la preuve
 * provient d'un chunk de questionnaire.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecarts', function (Blueprint $table) {
            $table->string('source_fichier')->nullable()->after('extrait_document');
            $table->unsignedInteger('question_numero')->nullable()->after('source_fichier');
            $table->text('question_texte')->nullable()->after('question_numero');
            $table->text('reponse_client')->nullable()->after('question_texte');
        });
    }

    public function down(): void
    {
        Schema::table('ecarts', function (Blueprint $table) {
            $table->dropColumn(['source_fichier', 'question_numero', 'question_texte', 'reponse_client']);
        });
    }
};
