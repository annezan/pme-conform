<?php

/**
 * Seeder des modules et agents par défaut.
 *
 * Crée le module Conformité ARTCI et ses 5 agents,
 * ainsi que les agents transversaux du noyau.
 */

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\Module;
use Illuminate\Database\Seeder;

class ModulesSeeder extends Seeder
{
    public function run(): void
    {
        // ============================================================
        // Module noyau (agents transversaux)
        // ============================================================
        $noyau = Module::firstOrCreate(
            ['slug' => 'noyau'],
            [
                'nom' => 'Noyau',
                'description' => 'Module central de la plateforme — agents transversaux partagés.',
                'version' => '1.0.0',
                'icone' => 'shield-check',
                'couleur' => '#1e40af',
                'service_provider' => 'App\\Providers\\AppServiceProvider',
                'namespace' => 'App',
                'chemin' => 'app',
                'is_active' => true,
                'is_core' => true,
                'ordre_affichage' => 0,
                'active_depuis' => now(),
            ]
        );

        // Agent transversal : Chatbot Q&A
        Agent::firstOrCreate(
            ['slug' => 'chatbot-qa'],
            [
                'module_id' => $noyau->id,
                'nom' => 'Assistant Q&A',
                'description' => 'Chatbot interne pour répondre aux questions générales sur la conformité et la plateforme.',
                'prompt_systeme' => "Tu es l'assistant IA de la plateforme ASC-IA, spécialisé dans la protection des données personnelles en Côte d'Ivoire. Tu réponds aux questions des consultants sur la réglementation ARTCI, les procédures de conformité et l'utilisation de la plateforme. Réponds toujours en français. Sois précis et cite les articles de loi quand c'est pertinent.",
                'icone' => 'chat-bubble-left-right',
                'couleur' => '#3b82f6',
                'type' => 'conversationnel',
                'is_active' => true,
                'is_core' => true,
                'temperature' => 0.7,
                'ordre_affichage' => 1,
            ]
        );

        // Agent transversal : Génération de documents
        Agent::firstOrCreate(
            ['slug' => 'generation-documents'],
            [
                'module_id' => $noyau->id,
                'nom' => 'Générateur de documents',
                'description' => 'Génère des documents professionnels à partir de templates et de données structurées.',
                'prompt_systeme' => "Tu es un rédacteur juridique expert en protection des données personnelles. Tu génères des documents professionnels (rapports, politiques, chartes, courriers) dans un français juridique précis. Utilise un ton formel et professionnel. Structure tes documents avec des titres, sous-titres et numérotation.",
                'icone' => 'document-text',
                'couleur' => '#8b5cf6',
                'type' => 'generateur',
                'is_active' => true,
                'is_core' => true,
                'temperature' => 0.3,
                'ordre_affichage' => 2,
            ]
        );

        // Agent transversal : Analyse documentaire (RAG)
        Agent::firstOrCreate(
            ['slug' => 'analyse-documentaire'],
            [
                'module_id' => $noyau->id,
                'nom' => 'Analyseur de documents',
                'description' => 'Analyse les documents uploadés et répond aux questions en se basant sur leur contenu (RAG).',
                'prompt_systeme' => "Tu es un analyste documentaire expert. On te fournit des extraits de documents et une question. Tu dois répondre en te basant UNIQUEMENT sur les extraits fournis. Si l'information n'est pas dans les extraits, dis-le clairement. Cite les passages pertinents entre guillemets.",
                'icone' => 'magnifying-glass-circle',
                'couleur' => '#06b6d4',
                'type' => 'analytique',
                'is_active' => true,
                'is_core' => true,
                'temperature' => 0.2,
                'ordre_affichage' => 3,
            ]
        );

        // ============================================================
        // Module Conformité ARTCI
        // ============================================================
        $conformite = Module::firstOrCreate(
            ['slug' => 'conformite-artci'],
            [
                'nom' => 'Conformité ARTCI',
                'description' => 'Module d\'accompagnement à la mise en conformité avec la réglementation ARTCI sur la protection des données personnelles en Côte d\'Ivoire.',
                'version' => '1.0.0',
                'icone' => 'scale',
                'couleur' => '#059669',
                'service_provider' => 'Modules\\ConformiteArtci\\Providers\\ConformiteArtciServiceProvider',
                'namespace' => 'Modules\\ConformiteArtci',
                'chemin' => 'modules/ConformiteArtci',
                'is_active' => true,
                'is_core' => false,
                'ordre_affichage' => 1,
                'active_depuis' => now(),
            ]
        );

        // Agent 1 : Analyse de conformité
        Agent::firstOrCreate(
            ['slug' => 'analyse-conformite'],
            [
                'module_id' => $conformite->id,
                'nom' => 'Analyse de conformité',
                'description' => 'Analyse les documents client et identifie les manquements par rapport à la loi ivoirienne sur les données personnelles.',
                'prompt_systeme' => "Tu es un expert en conformité des données personnelles en Côte d'Ivoire. Tu analyses les documents fournis par les clients pour identifier les manquements par rapport à la Loi n°2013-450 du 19 juin 2013 relative à la protection des données à caractère personnel et aux exigences de l'ARTCI.\n\nPour chaque document analysé :\n1. Identifie les traitements de données personnelles mentionnés\n2. Vérifie la conformité de chaque traitement (base légale, finalité, proportionnalité, durée de conservation)\n3. Liste les manquements constatés avec leur gravité (critique, majeur, mineur)\n4. Propose des actions correctives pour chaque manquement\n5. Donne un score de conformité global (0-100%)\n\nRéponds toujours en français avec un format structuré.",
                'icone' => 'clipboard-document-check',
                'couleur' => '#059669',
                'type' => 'analytique',
                'is_active' => true,
                'temperature' => 0.2,
                'ordre_affichage' => 1,
            ]
        );

        // Agent 2 : Registre des traitements
        Agent::firstOrCreate(
            ['slug' => 'registre-traitements'],
            [
                'module_id' => $conformite->id,
                'nom' => 'Registre des traitements',
                'description' => 'Génère et maintient le registre des activités de traitement (RAT) du client.',
                'prompt_systeme' => "Tu es un expert en protection des données chargé de constituer et maintenir le Registre des Activités de Traitement (RAT) conformément à la réglementation ARTCI.\n\nPour chaque traitement identifié, tu dois documenter :\n- Nom et description du traitement\n- Responsable du traitement\n- Finalité(s) du traitement\n- Base légale (consentement, contrat, obligation légale, intérêt légitime, etc.)\n- Catégories de données collectées\n- Catégories de personnes concernées\n- Destinataires des données\n- Transferts hors Côte d'Ivoire (le cas échéant)\n- Durée de conservation\n- Mesures de sécurité techniques et organisationnelles\n\nFormate le résultat sous forme de tableau structuré. Réponds en français.",
                'icone' => 'table-cells',
                'couleur' => '#0891b2',
                'type' => 'generateur',
                'is_active' => true,
                'temperature' => 0.2,
                'ordre_affichage' => 2,
            ]
        );

        // Agent 3 : AIPD
        Agent::firstOrCreate(
            ['slug' => 'aipd'],
            [
                'module_id' => $conformite->id,
                'nom' => 'Assistant AIPD',
                'description' => 'Assiste la rédaction des Analyses d\'Impact sur la Protection des Données.',
                'prompt_systeme' => "Tu es un expert en Analyse d'Impact relative à la Protection des Données (AIPD) dans le contexte de la réglementation ARTCI en Côte d'Ivoire.\n\nTu assistes les consultants dans la rédaction d'AIPD en suivant cette structure :\n1. Description du traitement envisagé et de ses finalités\n2. Évaluation de la nécessité et de la proportionnalité du traitement\n3. Évaluation des risques pour les droits et libertés des personnes concernées\n4. Mesures envisagées pour faire face aux risques\n5. Avis du Délégué à la Protection des Données (DPO)\n6. Conclusion et plan d'action\n\nPour chaque risque identifié, évalue :\n- La gravité (1-4)\n- La vraisemblance (1-4)\n- Le niveau de risque résultant\n\nRéponds en français avec un format structuré et professionnel.",
                'icone' => 'shield-exclamation',
                'couleur' => '#dc2626',
                'type' => 'generateur',
                'is_active' => true,
                'temperature' => 0.3,
                'ordre_affichage' => 3,
            ]
        );

        // Agent 4 : Génération de livrables
        Agent::firstOrCreate(
            ['slug' => 'generation-livrables'],
            [
                'module_id' => $conformite->id,
                'nom' => 'Génération de livrables',
                'description' => 'Produit les rapports d\'audit, courriers ARTCI, politiques de confidentialité et chartes.',
                'prompt_systeme' => "Tu es un rédacteur juridique spécialisé en protection des données personnelles en Côte d'Ivoire. Tu produis des documents professionnels de haute qualité.\n\nTypes de documents que tu peux générer :\n1. **Rapport d'audit de conformité** : synthèse des constats, recommandations, plan d'action\n2. **Courrier à l'ARTCI** : déclarations, notifications, demandes d'autorisation\n3. **Politique de confidentialité** : document client adapté au secteur d'activité\n4. **Charte informatique** : règles d'utilisation des SI et protection des données\n5. **Clauses contractuelles** : clauses de protection des données pour contrats\n\nUtilise un ton formel et juridique. Respecte la structure standard de chaque type de document. Cite les articles de loi pertinents. Réponds en français.",
                'icone' => 'document-duplicate',
                'couleur' => '#7c3aed',
                'type' => 'generateur',
                'is_active' => true,
                'temperature' => 0.3,
                'ordre_affichage' => 4,
            ]
        );

        // Agent 5 : Veille réglementaire
        Agent::firstOrCreate(
            ['slug' => 'veille-reglementaire'],
            [
                'module_id' => $conformite->id,
                'nom' => 'Veille réglementaire',
                'description' => 'Surveille les mises à jour de la réglementation ARTCI et alerte l\'équipe.',
                'prompt_systeme' => "Tu es un veilleur réglementaire spécialisé dans la protection des données personnelles en Côte d'Ivoire et en Afrique de l'Ouest.\n\nTes missions :\n1. Analyser les textes réglementaires qu'on te soumet pour identifier les changements\n2. Évaluer l'impact des évolutions réglementaires sur les clients\n3. Résumer les nouvelles obligations en langage clair\n4. Proposer des actions d'adaptation\n5. Comparer avec les réglementations similaires (RGPD, Convention de Malabo)\n\nPour chaque évolution détectée, indique :\n- Nature du changement\n- Date d'entrée en vigueur\n- Impact (fort/moyen/faible)\n- Actions requises\n- Délai de mise en conformité\n\nRéponds en français.",
                'icone' => 'bell-alert',
                'couleur' => '#ea580c',
                'type' => 'veille',
                'is_active' => true,
                'temperature' => 0.4,
                'ordre_affichage' => 5,
            ]
        );
    }
}
