<?php

/**
 * Migration de la table referentiels.
 *
 * Les referentiels sont les corpus legaux/reglementaires de reference
 * (ARTCI, ISO, normes sectorielles). Ils sont globaux a la plateforme
 * (partages entre tous les clients) et versionnes.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referentiels', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique(); // Ex: ARTCI-LOI-2013-450
            $table->string('titre');
            $table->text('description')->nullable();
            $table->string('autorite')->nullable(); // Ex: ARTCI, ISO, CEDEAO
            $table->string('version', 32)->nullable();
            $table->date('date_publication')->nullable();
            $table->date('date_entree_vigueur')->nullable();
            $table->enum('type', [
                'loi',
                'decret',
                'arrete',
                'directive',
                'norme',
                'guide',
                'autre',
            ])->default('loi');
                        $table->enum('statut', ['actif', 'obsolete', 'brouillon'])->default('actif');
            $table->text('contenu_extrait')->nullable(); // Texte integral extrait
            $table->string('source_url')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('statut');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referentiels');
    }
};
