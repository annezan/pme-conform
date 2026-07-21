<?php

/**
 * GenerateurQuestionnaireIA — Genere dynamiquement les questionnaires
 * d'audit conformite ARTCI a partir de l'organigramme du client (Methode 2).
 *
 * Pour chaque pole/service detecte, le LLM produit un questionnaire
 * adapte (questions ouvertes/fermees, themes ARTCI applicables :
 * biometrie, videosurveillance, cartographie SI, sous-traitance, etc.).
 *
 * En cas d'erreur LLM ou de modele indisponible, fallback sur un
 * questionnaire generique base sur les themes detectes.
 */

namespace App\Services\Methode2;

use App\Contracts\LLMConnectorInterface;
use App\Models\Mission;
use App\Models\Organigramme;
use App\Models\QuestionnaireGenere;
use App\Models\SecteurActivite;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class GenerateurQuestionnaireIA
{
    public function __construct(
        private LLMConnectorInterface $llm,
    ) {}

    /**
     * Regenere UN seul questionnaire : on re-prompt le LLM pour son couple
     * (pole, service) precis et on remplace les questions sur place.
     * Le titre, la description, les reponses deja saisies, le statut et la
     * publication ne sont pas touches. Si le LLM echoue, fallback hors-ligne.
     */
    public function regenererUn(QuestionnaireGenere $questionnaire): QuestionnaireGenere
    {
        $mission = $questionnaire->mission;
        $client = $mission?->client;
        $pole = trim((string) $questionnaire->pole);
        $service = trim((string) ($questionnaire->service ?? ''));
        $themes = $this->detecterThemes($pole, $service);

        $secteurs = $client
            ? ($client->secteursActivite->pluck('nom')->toArray()
                ?: ($client->secteurs_activite ?: ($client->secteur_activite ? [$client->secteur_activite] : [])))
            : [];

        $generation = $this->demanderAuLlm(
            $client?->raison_sociale ?? '—',
            $secteurs,
            $pole,
            $service,
            $themes,
        );

        $questionnaire->update([
            'questions' => $generation['questions'],
            'themes' => $themes,
            'source' => $generation['source'],
            // Si une description IA est revenue, on la rafraichit ; sinon on garde l'existante.
            'description' => $generation['description'] ?? $questionnaire->description,
        ]);

        return $questionnaire->fresh();
    }

    /**
     * Genere les questionnaires depuis un organigramme.
     * Retourne la liste des questionnaires crees.
     *
     * @return array<int, QuestionnaireGenere>
     */
    public function genererDepuisOrganigramme(
        Mission $mission,
        Organigramme $organigramme,
        User $initiateur,
    ): array {
        $structure = $organigramme->structure ?: [];
        if (empty($structure)) {
            // Mode upload sans structure parsee : fallback minimal sur 1 questionnaire generique
            $structure = [['pole' => 'Pole general', 'services' => [['nom' => null]]]];
        }

        $client = $mission->client;
        // Utiliser les secteurs normalisés d'abord, puis fallback sur legacy
        $secteurs = $client->secteursActivite->pluck('nom')->toArray() ?: 
                   ($client->secteurs_activite ?: 
                   ($client->secteur_activite ? [$client->secteur_activite] : []));

        $crees = [];
        foreach ($structure as $entree) {
            $pole = trim((string) ($entree['pole'] ?? 'Pole'));
            $services = $entree['services'] ?: [['nom' => null]];

            foreach ($services as $service) {
                $nomService = trim((string) ($service['nom'] ?? ''));
                $themes = $this->detecterThemes($pole, $nomService);

                $generation = $this->demanderAuLlm($client->raison_sociale, $secteurs, $pole, $nomService, $themes);

                $q = QuestionnaireGenere::create([
                    'mission_id' => $mission->id,
                    'organigramme_id' => $organigramme->id,
                    'pole' => $pole,
                    'service' => $nomService ?: null,
                    'titre' => $generation['titre'],
                    'description' => $generation['description'],
                    'questions' => $generation['questions'],
                    'source' => $generation['source'],
                    'themes' => $themes,
                    'statut' => 'brouillon',
                    'genere_par' => $initiateur->id,
                ]);
                $crees[] = $q;
            }
        }

        return $crees;
    }

    /**
     * Detecte les themes ARTCI pertinents selon la denomination du
     * pole/service. Sert au prompt + a etiqueter le questionnaire.
     */
    private function detecterThemes(string $pole, string $service): array
    {
        $hay = mb_strtolower($pole . ' ' . $service);
        $themes = [];

        if (preg_match('/(rh|ressources humaines|paie|administration|secretariat|accueil|securite|gardien)/u', $hay)) {
            $themes[] = 'biometrie';
            $themes[] = 'videosurveillance';
        }
        if (preg_match('/(it|informatique|sirh|systemes|reseau|si|technologie|technique|dsi|cybersecurite)/u', $hay)) {
            $themes[] = 'cartographie_applications';
            $themes[] = 'sous_traitance';
        }
        if (preg_match('/(commercial|marketing|client|crm|vente|prospection|communication)/u', $hay)) {
            $themes[] = 'cartographie_applications';
            $themes[] = 'consentement';
        }
        if (preg_match('/(finance|comptabilit|tresorerie|paiement|facturation)/u', $hay)) {
            $themes[] = 'sous_traitance';
            $themes[] = 'transferts_internationaux';
        }
        if (preg_match('/(juridique|conformite|risques|audit|compliance)/u', $hay)) {
            $themes[] = 'gouvernance';
            $themes[] = 'sous_traitance';
        }

        return array_values(array_unique($themes ?: ['donnees_personnelles_generales']));
    }

    private function demanderAuLlm(string $entreprise, array $secteurs, string $pole, string $service, array $themes): array
    {
        $secteurStr = implode(', ', $secteurs) ?: 'multi-sectoriel';
        $themesStr = implode(', ', $themes);
        $cible = trim("$pole" . ($service ? " - $service" : ''));

        $messages = [
            ['role' => 'system', 'content' => 'Tu es un expert auditeur conformite ARTCI (loi N°2013-450 - Cote d\'Ivoire). Tu generes des questionnaires d\'interview adaptes au contexte du pole/service du client. Tu reponds UNIQUEMENT en JSON valide, sans markdown, sans texte avant ou apres.'],
            ['role' => 'user', 'content' => $this->prompt($entreprise, $secteurStr, $cible, $themesStr)],
        ];

        try {
            // 1500 tokens suffisent pour 8-12 questions courtes en JSON.
            // Au-dela le modele 3B met >2min sur CPU et le timeout HTTP saute.
            $reponse = $this->llm->completer($messages, null, 0.4, 1500);
            $json = $this->extraireJson($reponse['content'] ?? '');
            if ($json && is_array($json) && ! empty($json['questions'])) {
                $questions = $this->normaliserQuestions($json['questions']);
                if (! empty($questions)) {
                    return [
                        'titre' => $json['titre'] ?? "Questionnaire $cible",
                        'description' => $json['description'] ?? null,
                        'questions' => $questions,
                        'source' => 'ia',
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('GenerateurQuestionnaireIA : LLM indisponible, fallback', ['error' => $e->getMessage()]);
        }

        return $this->fallback($cible, $themes);
    }

    private function prompt(string $entreprise, string $secteurs, string $cible, string $themes): string
    {
        return <<<PROMPT
Genere un questionnaire d'audit conformite ARTCI pour le pole/service "{$cible}" de l'entreprise "{$entreprise}" (secteurs : {$secteurs}).

Themes ARTCI a couvrir prioritairement : {$themes}.

Le questionnaire doit comporter 8 a 10 questions COURTES (max 25 mots chacune), ordonnees du general au specifique :
  - description des activites du pole
  - donnees personnelles manipulees (categories, sources, finalites)
  - bases legales du traitement
  - duree de conservation
  - destinataires internes/externes / sous-traitants
  - mesures de securite (controle d'acces, sauvegardes, chiffrement)
  - selon themes : biometrie (dispositifs, finalites, durees), videosurveillance
    (nombre cameras, finalites, conservation, accessibilites),
    cartographie applications (logiciels, hebergement, fournisseurs),
    sous-traitance (contrats, transferts hors CEDEAO).

Reponds STRICTEMENT au format JSON :
{
  "titre": "Questionnaire ARTCI - [pole/service]",
  "description": "courte description (1-2 phrases) du perimetre du questionnaire",
  "questions": [
    {"numero": 1, "texte": "...", "type": "ouverte|liste|oui_non", "themes": ["..."], "options": [...]?}
  ]
}
PROMPT;
    }

    private function extraireJson(string $contenu): ?array
    {
        $contenu = trim($contenu);
        // Supprimer eventuels backticks markdown
        $contenu = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $contenu);
        $debut = mb_strpos($contenu, '{');
        $fin = mb_strrpos($contenu, '}');
        if ($debut === false || $fin === false || $fin < $debut) {
            return null;
        }
        $bloc = mb_substr($contenu, $debut, $fin - $debut + 1);
        $decode = json_decode($bloc, true);

        return is_array($decode) ? $decode : null;
    }

    private function normaliserQuestions(array $questions): array
    {
        $result = [];
        $i = 1;
        foreach ($questions as $q) {
            if (! is_array($q) || empty($q['texte'])) {
                continue;
            }
            $result[] = [
                'numero' => (int) ($q['numero'] ?? $i),
                'texte' => mb_substr((string) $q['texte'], 0, 1000),
                'type' => in_array(($q['type'] ?? ''), ['ouverte', 'liste', 'oui_non'], true) ? $q['type'] : 'ouverte',
                'themes' => array_values((array) ($q['themes'] ?? [])),
                'options' => isset($q['options']) ? array_map(fn ($o) => (string) $o, (array) $q['options']) : [],
            ];
            $i++;
        }

        return $result;
    }

    /**
     * Fallback hors-ligne : produit un questionnaire generique base sur
     * les themes detectes. Permet d'utiliser la fonction meme sans LLM.
     */
    private function fallback(string $cible, array $themes): array
    {
        $base = [
            ['texte' => "Decrivez les principales activites du pole/service \"{$cible}\".", 'type' => 'ouverte'],
            ['texte' => 'Quelles categories de donnees personnelles sont manipulees ? (etat-civil, contact, financier, sante, biometrique, etc.)', 'type' => 'ouverte'],
            ['texte' => 'Quelles sont les finalites de la collecte de ces donnees ?', 'type' => 'ouverte'],
            ['texte' => "Quelle est la base legale du traitement ? (consentement, contrat, obligation legale, interet legitime)", 'type' => 'liste', 'options' => ['Consentement', 'Contrat', 'Obligation legale', 'Interet legitime', 'Mission interet public']],
            ['texte' => 'Quelle est la duree de conservation des donnees ?', 'type' => 'ouverte'],
            ['texte' => 'Qui sont les destinataires internes (services, equipes) de ces donnees ?', 'type' => 'ouverte'],
            ['texte' => "Existe-t-il des sous-traitants ou prestataires externes traitant ces donnees ? Lesquels ?", 'type' => 'ouverte'],
            ['texte' => "Quelles mesures de securite sont en place ? (controle d'acces, chiffrement, sauvegarde)", 'type' => 'ouverte'],
        ];

        $additions = [];
        if (in_array('biometrie', $themes, true)) {
            $additions[] = ['texte' => 'Quels dispositifs biometriques utilisez-vous ? (empreintes, reconnaissance faciale, contour main)', 'type' => 'ouverte', 'themes' => ['biometrie']];
            $additions[] = ['texte' => 'Combien de dispositifs biometriques sont deployes par site ?', 'type' => 'ouverte', 'themes' => ['biometrie']];
        }
        if (in_array('videosurveillance', $themes, true)) {
            $additions[] = ['texte' => 'Combien de cameras sont installees (interieures vs exterieures) par site ?', 'type' => 'ouverte', 'themes' => ['videosurveillance']];
            $additions[] = ['texte' => 'Quelle est la duree de conservation des enregistrements video ?', 'type' => 'ouverte', 'themes' => ['videosurveillance']];
        }
        if (in_array('cartographie_applications', $themes, true)) {
            $additions[] = ['texte' => 'Quels logiciels metiers et applications utilisez-vous ? Pour quelles finalites ?', 'type' => 'ouverte', 'themes' => ['cartographie_applications']];
            $additions[] = ['texte' => 'Ou sont heberges les serveurs de ces applications ? (interne, cloud, pays)', 'type' => 'ouverte', 'themes' => ['cartographie_applications']];
        }
        if (in_array('transferts_internationaux', $themes, true)) {
            $additions[] = ['texte' => "Y a-t-il des transferts de donnees hors CEDEAO ? Vers quels pays ?", 'type' => 'ouverte', 'themes' => ['transferts_internationaux']];
        }
        if (in_array('sous_traitance', $themes, true)) {
            $additions[] = ['texte' => "Disposez-vous de contrats avec clauses de protection des donnees pour vos sous-traitants ?", 'type' => 'oui_non', 'themes' => ['sous_traitance']];
        }

        $toutes = array_merge($base, $additions);
        $i = 1;
        $questions = [];
        foreach ($toutes as $q) {
            $questions[] = [
                'numero' => $i++,
                'texte' => $q['texte'],
                'type' => $q['type'] ?? 'ouverte',
                'themes' => $q['themes'] ?? [],
                'options' => $q['options'] ?? [],
            ];
        }

        return [
            'titre' => "Questionnaire ARTCI - $cible",
            'description' => 'Questionnaire generique base sur les themes detectes (LLM indisponible).',
            'questions' => $questions,
            'source' => 'manuel',
        ];
    }
}
