<?php

/**
 * Remplace l'index UNIQUE(email) sur users par un index UNIQUE partiel qui
 * n'inclut que les users NON soft-deleted (deleted_at IS NULL).
 *
 * Symptome initial : impossible de recreer un compte avec un email d'un
 * user precedemment rejete (soft-delete). Laravel validait OK (Rule::unique
 * -> whereNull('deleted_at')) mais Postgres rejetait en insert car l'index
 * unique classique consideraient TOUS les enregistrements, y compris les
 * soft-deleted.
 *
 * Apres cette migration : deux users peuvent avoir le meme email si l'un
 * est soft-deleted (typiquement apres un rejet ou une desactivation
 * definitive). Le nouveau user est le seul actif, les anciens restent en
 * base pour la tracabilite.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // L'index unique est protege par une CONSTRAINT (produite par Laravel
        // Schema::unique). Il faut donc dropper la contrainte, puis creer
        // l'index partiel qui accepte les doublons quand deleted_at n'est pas NULL.
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_email_unique');
        DB::statement('DROP INDEX IF EXISTS users_email_unique');
        DB::statement('CREATE UNIQUE INDEX users_email_unique ON users (email) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        // Rollback : drop l'index partiel puis recree la contrainte unique
        // classique. Si des doublons existent (soft-deleted avec meme email),
        // cette commande echouera — c'est volontaire, il faut alors nettoyer
        // manuellement avant de rollback.
        DB::statement('DROP INDEX IF EXISTS users_email_unique');
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_email_unique UNIQUE (email)');
    }
};
