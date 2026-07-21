<?php

/**
 * Tables chartes + signatures.
 *
 * chartes = versions des chartes publiables par l'administration (ex: charte IA,
 * charte de sous-traitance) que tout client doit signer avant usage.
 * signatures = tracabilite immuable des signatures par user + client.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chartes', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['charte_ia', 'charte_sous_traitance', 'cgu', 'accord_confidentialite', 'autre']);
            $table->string('titre');
            $table->string('version', 20); // 1.0, 1.1, 2.0...
            $table->longText('contenu_html'); // rendu dans l'UI
            $table->string('hash_contenu', 64); // SHA-256 immuable par version
            $table->boolean('active')->default(true);
            $table->boolean('obligatoire')->default(false); // oblige la signature avant d'utiliser certaines features
            $table->timestamp('publiee_le');
            $table->timestamps();

            $table->unique(['type', 'version']);
            $table->index(['type', 'active']);
        });

        Schema::create('signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('charte_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('client_id')->constrained(); // entreprise signataire
            $table->string('hash_contenu_signe', 64); // hash lu au moment de la signature (anti-tampering)
            $table->string('ip_signature', 45);
            $table->string('user_agent_signature')->nullable();
            $table->enum('statut', ['signee', 'revoquee'])->default('signee');
            $table->timestamp('signee_le');
            $table->timestamp('revoquee_le')->nullable();
            $table->text('raison_revocation')->nullable();
            $table->timestamps();

            $table->index(['charte_id', 'user_id']);
            $table->index(['client_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signatures');
        Schema::dropIfExists('chartes');
    }
};
