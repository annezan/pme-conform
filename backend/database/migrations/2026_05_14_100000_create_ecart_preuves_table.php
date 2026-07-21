<?php

/**
 * Preuves justificatives attachees a un ecart de conformite.
 * Permet au consultant ou au client de documenter la correction d'un ecart
 * par l'upload de pieces (contrat signe, capture, procedure, etc.).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecart_preuves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ecart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('libelle');
            $table->text('description')->nullable();
            $table->string('nom_fichier_original');
            $table->string('chemin');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('taille_octets');
            $table->timestamps();
            $table->softDeletes();

            $table->index('ecart_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecart_preuves');
    }
};
