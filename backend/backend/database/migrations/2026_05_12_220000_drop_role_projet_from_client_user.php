<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Supprimer le champ role_projet de la table client_user
     * car les rôles sont gérés par Spatie Permission au niveau des utilisateurs
     */
    public function up(): void
    {
        Schema::table('client_user', function (Blueprint $table) {
            $table->dropColumn('role_projet');
        });
    }

    public function down(): void
    {
        Schema::table('client_user', function (Blueprint $table) {
            $table->string('role_projet')->nullable();
        });
    }
};
