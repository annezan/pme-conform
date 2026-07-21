<?php

/**
 * Table traitements — Fiches de traitement de donnees personnelles.
 *
 * Chaque client (toutes tailles) saisit ses traitements qui alimentent
 * le registre KYC genere a la demande.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traitements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 32)->unique();

            $table->string('nom');
            $table->enum('statut', ['brouillon', 'valide', 'archive'])->default('brouillon');

            // Finalites
            $table->text('finalite_principale');
            $table->json('finalites_secondaires')->nullable();
            $table->json('bases_legales'); // consentement, contrat, obligation_legale, interet_legitime, mission_interet_public, sauvegarde

            // Categories de donnees
            $table->json('categories_personnes');   // salaries, clients, prospects, fournisseurs, mineurs...
            $table->json('categories_donnees');     // identite, contact, banque, sante, localisation, biometrique...
            $table->boolean('donnees_sensibles')->default(false);
            $table->json('donnees_sensibles_types')->nullable();

            // Durees de conservation (en mois)
            $table->integer('duree_conservation_active_mois')->nullable();
            $table->integer('duree_archivage_mois')->nullable();
            $table->text('justification_duree')->nullable();

            // Destinataires et transferts internationaux
            $table->json('destinataires_internes')->nullable();
            $table->json('destinataires_externes')->nullable();
            $table->boolean('transfert_hors_cedeao')->default(false);
            $table->json('pays_destinataires')->nullable();
            $table->string('base_transfert')->nullable(); // bcr, cct, consentement, derogation

            // Mesures de securite
            $table->json('mesures_techniques')->nullable();         // chiffrement, mfa, logs...
            $table->json('mesures_organisationnelles')->nullable(); // sensibilisation, contrats...

            // Acteurs
            $table->string('responsable_traitement_nom')->nullable();
            $table->json('sous_traitants')->nullable(); // [{nom, role, pays, dpa}]
            $table->foreignId('saisi_par')->constrained('users');
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('valide_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['client_id', 'statut']);
            $table->index('saisi_par');
        });

        Schema::create('traitement_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('traitement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('modifie_par')->constrained('users');
            $table->json('snapshot'); // etat complet du traitement a cette date
            $table->text('commentaire')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('traitement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traitement_revisions');
        Schema::dropIfExists('traitements');
    }
};
