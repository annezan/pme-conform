<?php

/**
 * Ajoute la valeur 'methode_3' (Audit Flash) au check constraint
 * de missions.methode. Methode 3 = questionnaire fixe de 10 items
 * "Scan penal du dirigeant", sans matrice ni organigramme.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE missions DROP CONSTRAINT IF EXISTS missions_methode_check');
        DB::statement("ALTER TABLE missions ADD CONSTRAINT missions_methode_check CHECK (methode IN ('methode_1', 'methode_2', 'methode_3'))");
    }

    public function down(): void
    {
        DB::statement("UPDATE missions SET methode = 'methode_1' WHERE methode = 'methode_3'");
        DB::statement('ALTER TABLE missions DROP CONSTRAINT IF EXISTS missions_methode_check');
        DB::statement("ALTER TABLE missions ADD CONSTRAINT missions_methode_check CHECK (methode IN ('methode_1', 'methode_2'))");
    }
};
