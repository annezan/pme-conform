<?php

/**
 * Ajoute une colonne `service` (nullable) sur le pivot client_user pour
 * permettre un rattachement plus fin :
 *   - pole non null + service NULL  => l'utilisateur supervise l'integralite du pole
 *   - pole non null + service non null => l'utilisateur ne voit que ce service
 *
 * Regle metier : par client, un couple (pole, service) doit etre unique.
 * On ajoute donc un index unique partiel pour le cas service IS NOT NULL,
 * et on garde l'unicite simple sur pole pour le cas service IS NULL.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_user', function (Blueprint $table) {
            $table->string('service')->nullable()->after('pole');
        });

        // Index unique composite (client_id, pole, service) en tolerant les NULL
        // grace a COALESCE — necessaire avec PostgreSQL qui considere NULL != NULL.
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX client_user_client_pole_service_unique
            ON client_user (client_id, pole, COALESCE(service, ''))
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS client_user_client_pole_service_unique');
        Schema::table('client_user', function (Blueprint $table) {
            $table->dropColumn('service');
        });
    }
};
