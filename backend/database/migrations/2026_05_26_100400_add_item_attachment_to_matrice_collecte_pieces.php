<?php

/**
 * Rattache les pieces de la matrice de collecte a un item precis.
 *
 * Pour chaque champ/question de la matrice (pole_code + item_code), le
 * client peut desormais uploader un ou plusieurs documents justificatifs
 * en plus (ou a la place) d'une reponse textuelle. Les anciennes pieces
 * non rattachees conservent pole_code/item_code = null.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matrice_collecte_pieces', function (Blueprint $table) {
            $table->string('pole_code', 100)->nullable()->after('uploade_par');
            $table->string('item_code', 100)->nullable()->after('pole_code');

            $table->index(['matrice_collecte_id', 'pole_code', 'item_code'], 'matrice_pieces_item_idx');
        });
    }

    public function down(): void
    {
        Schema::table('matrice_collecte_pieces', function (Blueprint $table) {
            $table->dropIndex('matrice_pieces_item_idx');
            $table->dropColumn(['pole_code', 'item_code']);
        });
    }
};
