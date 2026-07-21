<?php

/**
 * Refonte des traitements au modele MOBISOFT.
 *
 * RESET : on supprime les anciennes tables `traitements` et
 * `traitement_revisions`, ainsi que les `ecarts.traitement_id`/etc.
 * eventuels, et on recree une structure complete inspiree du registre
 * MOBISOFT (1 fiche par traitement avec sous-tables).
 *
 * Tables creees :
 *  - clients_organismes        : 1 par client (responsable + DPO)
 *  - traitements               : fiche maitre du traitement (designation,
 *                                code, finalite, service, dates...)
 *  - traitement_supports       : materiels/logiciels/papier
 *  - traitement_actes          : actes de traitement + bases legales
 *  - traitement_personnes      : categories de personnes concernees
 *  - traitement_categories_donnees : donnees collectees (categorie, detail, origine)
 *  - traitement_transferts     : transferts hors CEDEAO
 *  - traitement_mesures_securite : mesures par categorie (controle acces,
 *                                tracabilite, sauvegarde, chiffrement, etc.)
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('traitement_revisions');
        Schema::dropIfExists('traitements');

        Schema::create('clients_organismes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->unique()->constrained()->cascadeOnDelete();
            // Responsable de traitement (organisme = client)
            $table->string('rt_nom')->nullable();
            $table->string('rt_fonction')->nullable();
            $table->text('rt_adresse')->nullable();
            $table->string('rt_code_postal', 20)->nullable();
            $table->string('rt_ville')->nullable();
            $table->string('rt_pays')->nullable();
            $table->string('rt_telephone', 30)->nullable();
            $table->string('rt_email')->nullable();
            // DPO (Delegue a la Protection des Donnees)
            $table->string('dpo_nom')->nullable();
            $table->text('dpo_adresse')->nullable();
            $table->string('dpo_code_postal', 20)->nullable();
            $table->string('dpo_ville')->nullable();
            $table->string('dpo_pays')->nullable();
            $table->string('dpo_telephone', 30)->nullable();
            $table->string('dpo_email')->nullable();
            $table->timestamps();
        });

        Schema::create('traitements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 32)->unique();
            $table->string('code_finalite', 32)->nullable();
            $table->string('designation');
            $table->text('description')->nullable();
            $table->string('direction_pole')->nullable();
            $table->json('services_charges')->nullable();
            $table->json('sources')->nullable();
            $table->boolean('contient_donnees_sensibles')->default(false);
            $table->boolean('transfert_hors_cedeao')->default(false);
            $table->date('date_creation_fiche')->nullable();
            $table->date('date_maj_fiche')->nullable();
            $table->enum('statut', ['brouillon', 'valide', 'archive'])->default('brouillon');
            $table->foreignId('saisi_par')->constrained('users');
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('valide_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['client_id', 'statut']);
        });

        Schema::create('traitement_supports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('traitement_id')->constrained()->cascadeOnDelete();
            $table->enum('categorie', ['materiel', 'logiciel', 'papier', 'autre']);
            $table->string('type')->nullable();
            $table->string('marque_version')->nullable();
            $table->text('precision')->nullable();
            $table->timestamps();
        });

        Schema::create('traitement_actes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('traitement_id')->constrained()->cascadeOnDelete();
            $table->string('acte');
            $table->string('base_legale');
            $table->text('precision')->nullable();
            $table->timestamps();
        });

        Schema::create('traitement_personnes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('traitement_id')->constrained()->cascadeOnDelete();
            $table->string('categorie');
            $table->text('documentation_source')->nullable();
            $table->timestamps();
        });

        Schema::create('traitement_categories_donnees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('traitement_id')->constrained()->cascadeOnDelete();
            $table->string('categorie_principale');
            $table->string('detail');
            $table->enum('origine', ['direct', 'indirect'])->default('direct');
            $table->boolean('est_sensible')->default(false);
            $table->timestamps();

            $table->index(['traitement_id', 'categorie_principale']);
        });

        Schema::create('traitement_transferts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('traitement_id')->constrained()->cascadeOnDelete();
            $table->string('organe');
            $table->string('pays');
            $table->text('garantie')->nullable();
            $table->string('sens_groupe')->nullable();
            $table->timestamps();
        });

        Schema::create('traitement_mesures_securite', function (Blueprint $table) {
            $table->id();
            $table->foreignId('traitement_id')->constrained()->cascadeOnDelete();
            $table->enum('categorie', [
                'controle_acces',
                'tracabilite',
                'protection_logiciels',
                'sauvegarde',
                'chiffrement',
                'controle_sous_traitants',
                'autres',
            ]);
            $table->text('description');
            $table->timestamps();

            $table->index(['traitement_id', 'categorie']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traitement_mesures_securite');
        Schema::dropIfExists('traitement_transferts');
        Schema::dropIfExists('traitement_categories_donnees');
        Schema::dropIfExists('traitement_personnes');
        Schema::dropIfExists('traitement_actes');
        Schema::dropIfExists('traitement_supports');
        Schema::dropIfExists('traitements');
        Schema::dropIfExists('clients_organismes');
    }
};
