<?php

/**
 * Ajoute des champs pour la detection automatique de questionnaires
 * parmi les documents uploades par le client.
 *
 * - is_questionnaire : true si le parser a detecte une structure Q/R
 * - nb_questions : nombre total de questions detectees
 * - nb_questions_repondues : combien de questions ont une reponse non vide
 * - questions_data : JSON [{numero, question, reponse, repondu}, ...]
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->boolean('is_questionnaire')->default(false)->after('is_confidentiel');
            $table->unsignedInteger('nb_questions')->nullable()->after('is_questionnaire');
            $table->unsignedInteger('nb_questions_repondues')->nullable()->after('nb_questions');
            $table->json('questions_data')->nullable()->after('nb_questions_repondues');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['is_questionnaire', 'nb_questions', 'nb_questions_repondues', 'questions_data']);
        });
    }
};
