<?php

/**
 * Stocke le nom d'origine et la taille du fichier organigramme uploade
 * par le client pour permettre un aperçu et un telechargement avec le
 * vrai nom de fichier.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organigrammes', function (Blueprint $table) {
            $table->string('fichier_nom_original')->nullable()->after('fichier_mime');
            $table->unsignedInteger('fichier_taille_octets')->nullable()->after('fichier_nom_original');
        });
    }

    public function down(): void
    {
        Schema::table('organigrammes', function (Blueprint $table) {
            $table->dropColumn(['fichier_nom_original', 'fichier_taille_octets']);
        });
    }
};
