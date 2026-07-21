<?php

/**
 * registres_kyc : PDF/DOCX generes dynamiquement a partir des traitements
 * valides d'un client. Chaque generation est horodatee et empreinte (hash).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registres_kyc', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('genere_par')->constrained('users');
            $table->string('reference', 32)->unique(); // REG-2026-001
            $table->integer('nb_traitements');
            $table->json('snapshot_traitements'); // [{id, reference, revision_id}]
            $table->string('fichier_path');
            $table->string('hash_fichier', 64); // SHA-256
            $table->enum('format', ['pdf', 'docx', 'xlsx'])->default('pdf');
            $table->enum('statut_generation', ['en_cours', 'termine', 'erreur'])->default('en_cours');
            $table->text('erreur_message')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'statut_generation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registres_kyc');
    }
};
