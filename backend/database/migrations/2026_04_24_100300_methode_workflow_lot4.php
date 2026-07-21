<?php

/**
 * Lot 4 — Workflow Methode 1 / Methode 2.
 *
 *   - missions.methode : 'methode_1' (questionnaires conçus par ASC) ou
 *     'methode_2' (matrice -> organigramme -> questionnaires generes par IA).
 *
 *   - matrices_collecte : 1 par mission de Methode 2. Stocke les reponses
 *     du client a la matrice initiale (bloc texte ou JSON structure).
 *
 *   - matrice_collecte_pieces : pieces de conviction uploadees par le
 *     client en reponse a la matrice (modele documents leger).
 *
 *   - organigrammes : 1 par mission de Methode 2. Soit fichier uploade,
 *     soit JSON arborescent structure dans l'app.
 *
 *   - questionnaires_generes : questionnaires emis par l'IA depuis
 *     l'organigramme. 1 par pole/service detecte.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->enum('methode', ['methode_1', 'methode_2'])->default('methode_1')->after('type');
        });

        Schema::create('matrices_collecte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->unique()->constrained()->cascadeOnDelete();
            $table->enum('statut', ['a_remplir', 'en_cours', 'remise', 'validee'])->default('a_remplir');
            $table->json('reponses')->nullable();         // {pole_1: {...}, pole_2: {...}}
            $table->text('reponses_libres')->nullable();  // texte libre du client
            $table->timestamp('envoyee_a')->nullable();   // date d'envoi email au client
            $table->timestamp('remise_a')->nullable();
            $table->foreignId('validee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validee_at')->nullable();
            $table->timestamps();
        });

        Schema::create('matrice_collecte_pieces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matrice_collecte_id')->constrained('matrices_collecte')->cascadeOnDelete();
            $table->foreignId('uploade_par')->constrained('users');
            $table->string('libelle');
            $table->string('chemin');
            $table->string('mime', 100)->nullable();
            $table->integer('taille_octets')->default(0);
            $table->timestamps();
        });

        Schema::create('organigrammes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->unique()->constrained()->cascadeOnDelete();
            $table->enum('mode', ['upload', 'formulaire'])->default('formulaire');
            $table->json('structure')->nullable(); // [{pole, services:[{nom, postes:[]}]}]
            $table->string('fichier_chemin')->nullable();
            $table->string('fichier_mime', 100)->nullable();
            $table->enum('statut', ['en_cours', 'fige'])->default('en_cours');
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('valide_at')->nullable();
            $table->timestamps();
        });

        Schema::create('questionnaires_generes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organigramme_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pole');
            $table->string('service')->nullable();
            $table->string('titre');
            $table->text('description')->nullable();
            $table->json('questions');             // [{numero, texte, type, options[]?, attendu?}]
            $table->enum('source', ['ia', 'manuel'])->default('ia');
            $table->json('themes')->nullable();    // ['biometrie', 'video', 'carto', 'sous_traitance', ...]
            $table->enum('statut', ['brouillon', 'envoye', 'rempli', 'valide'])->default('brouillon');
            $table->json('reponses')->nullable(); // [{numero, reponse, repondu, repondu_par, repondu_at}]
            $table->foreignId('genere_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rempli_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('envoye_a')->nullable();
            $table->timestamp('rempli_a')->nullable();
            $table->timestamps();

            $table->index(['mission_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questionnaires_generes');
        Schema::dropIfExists('organigrammes');
        Schema::dropIfExists('matrice_collecte_pieces');
        Schema::dropIfExists('matrices_collecte');
        Schema::table('missions', function (Blueprint $table) {
            $table->dropColumn('methode');
        });
    }
};
