<?php

/**
 * Ajoute la colonne `documents_sources` (JSON) a ecarts.
 *
 * Permet de citer N documents qui presentent le meme ecart sur la meme exigence,
 * sans dupliquer l'ecart lui-meme. Chaque entree contient :
 *   {document_id, titre, nom_fichier, extrait_document, score_similarite,
 *    type_ecart, question_numero?, question_texte?, reponse_client?}
 *
 * Le champ document_id (singulier) reste rempli avec la source "primaire"
 * (la plus pertinente) pour la retro-compat des rapports Word/PPTX.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecarts', function (Blueprint $table) {
            $table->json('documents_sources')->nullable()->after('extrait_document');
        });
    }

    public function down(): void
    {
        Schema::table('ecarts', function (Blueprint $table) {
            $table->dropColumn('documents_sources');
        });
    }
};
