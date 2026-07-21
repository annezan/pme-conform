<?php

/**
 * Enrichit la table clients avec les informations d'entreprise
 * utilisees par les nouvelles fonctionnalites PME-CONFORM.
 *
 * Note : ces champs s'appliquent a TOUS les types de clients
 * (PME, grande entreprise, administration, autre).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->enum('type_structure', ['pme', 'grande_entreprise', 'administration', 'association', 'autre'])
                  ->default('pme')->after('statut');
            $table->string('numero_rccm', 50)->nullable()->after('numero_registre_commerce');
            $table->string('numero_cc', 50)->nullable()->after('numero_rccm'); // Compte contribuable
            $table->integer('effectif')->nullable()->after('numero_cc');
            $table->decimal('chiffre_affaires_mfcfa', 14, 2)->nullable()->after('effectif');
            $table->date('date_creation_entreprise')->nullable()->after('chiffre_affaires_mfcfa');
            $table->string('logo_path')->nullable()->after('date_creation_entreprise');
            $table->timestamp('onboarding_complete_at')->nullable()->after('logo_path');

            $table->index('type_structure');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['type_structure']);
            $table->dropColumn([
                'type_structure',
                'numero_rccm',
                'numero_cc',
                'effectif',
                'chiffre_affaires_mfcfa',
                'date_creation_entreprise',
                'logo_path',
                'onboarding_complete_at',
            ]);
        });
    }
};
