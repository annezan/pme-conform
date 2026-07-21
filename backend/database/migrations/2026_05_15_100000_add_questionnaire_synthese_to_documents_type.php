<?php

/**
 * Ajoute la valeur `questionnaire_synthese` au check `documents_type_check`.
 *
 * GapAnalysisService materialise chaque questionnaire renseigne en Document
 * virtuel (texte concatene question/reponse) pour l'alimenter au moteur RAG.
 * Ce type n'etait pas autorise par le check Postgres pose lors de la creation
 * de la table.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TYPES_AUTORISES = [
        'document_client',
        'rapport_audit',
        'politique',
        'registre',
        'aipd',
        'courrier_artci',
        'charte',
        'modele',
        'questionnaire_synthese',
        'autre',
    ];

    public function up(): void
    {
        $valeurs = collect(self::TYPES_AUTORISES)
            ->map(fn ($v) => "'" . str_replace("'", "''", $v) . "'")
            ->implode(', ');

        DB::statement('ALTER TABLE documents DROP CONSTRAINT IF EXISTS documents_type_check');
        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_type_check CHECK (type IN ({$valeurs}))");
    }

    public function down(): void
    {
        $valeursLegacy = "'document_client', 'rapport_audit', 'politique', 'registre', 'aipd', 'courrier_artci', 'charte', 'modele', 'autre'";

        DB::statement('ALTER TABLE documents DROP CONSTRAINT IF EXISTS documents_type_check');
        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_type_check CHECK (type IN ({$valeursLegacy}))");
    }
};
