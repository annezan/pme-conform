<?php

/**
 * Cree la table plan_action_item_preuves et ajoute les colonnes verdict
 * sur plan_action_items + le marqueur de soumission sur plans_actions.
 *
 * Workflow :
 *  1. Client uploade des preuves sur les items du plan (dans le drawer kanban).
 *  2. Client clique "Soumettre au consultant" sur PlanActionDetail.
 *     -> plans_actions.soumis_le = now()
 *     -> Job VerifierPreuvesPlanJob dispatche.
 *  3. Pour chaque item avec au moins une preuve : extraction texte + appel LLM
 *     qui compare le contenu des preuves a la recommandation de l'ecart lie,
 *     et renvoie verdict + justification.
 *     -> plan_action_items.verdict_correction / justification_correction / verifie_le.
 *  4. Notification au consultant ASC en fin de job.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_action_item_preuves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_action_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('libelle');
            $table->text('description')->nullable();
            $table->string('nom_fichier_original');
            $table->string('chemin');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('taille_octets');
            // Cache du texte extrait : evite de relancer ExtractorFactory a chaque
            // verification (utile si on rejoue la verification ou si la preuve
            // est evaluee plusieurs fois apres ajouts d'autres preuves).
            $table->text('contenu_extrait')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('plan_action_item_id');
        });

        Schema::table('plan_action_items', function (Blueprint $table) {
            // conforme : la preuve satisfait pleinement la recommandation.
            // partielle : couvre une partie mais des elements manquent.
            // non_conforme : ne repond pas a la recommandation.
            // non_evalue : pas encore evalue (defaut tant que soumission/verif pas faite).
            $table->string('verdict_correction', 20)->nullable()->after('notes_consultant');
            $table->text('justification_correction')->nullable()->after('verdict_correction');
            $table->timestamp('verifie_le')->nullable()->after('justification_correction');
        });

        Schema::table('plans_actions', function (Blueprint $table) {
            // Marqueur de soumission par le client : declenche la verification LLM.
            // null = pas encore soumis. Date = soumis pour validation.
            $table->timestamp('soumis_le')->nullable()->after('accepte_le');
            $table->foreignId('soumis_par')->nullable()->after('soumis_le')->constrained('users')->nullOnDelete();
            // Suivi du job de verification : en_attente / en_cours / terminee / erreur.
            // Permet a l'UI d'afficher la progression sans poller le job.
            $table->string('verification_statut', 20)->nullable()->after('soumis_par');
            $table->unsignedSmallInteger('verification_progression_pct')->nullable()->after('verification_statut');
        });
    }

    public function down(): void
    {
        Schema::table('plans_actions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('soumis_par');
            $table->dropColumn(['soumis_le', 'verification_statut', 'verification_progression_pct']);
        });

        Schema::table('plan_action_items', function (Blueprint $table) {
            $table->dropColumn(['verdict_correction', 'justification_correction', 'verifie_le']);
        });

        Schema::dropIfExists('plan_action_item_preuves');
    }
};
