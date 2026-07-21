<?php

/**
 * Ajoute un lien optionnel entre une piece de matrice et un Document.
 *
 * Justification : chaque piece justificative uploadee via la matrice doit
 * apparaitre aussi dans /mes-documents (espace documentaire client global).
 * Cette colonne permet de partager la meme entree Document (et donc le meme
 * fichier Spatie media) entre les deux features.
 *
 * Cascade ON DELETE SET NULL : si le Document est supprime, la piece reste
 * mais perd son lien (chemin local reste dispo via la colonne `chemin`).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matrice_collecte_pieces', function (Blueprint $table) {
            $table->foreignId('document_id')
                ->nullable()
                ->after('matrice_collecte_id')
                ->constrained('documents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('matrice_collecte_pieces', function (Blueprint $table) {
            $table->dropConstrainedForeignId('document_id');
        });
    }
};
