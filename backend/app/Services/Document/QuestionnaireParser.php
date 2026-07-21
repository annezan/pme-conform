<?php

/**
 * Service QuestionnaireParser — Detecte et extrait la structure Q/R d'un document.
 *
 * Reconnait plusieurs conventions de numerotation :
 *   "Question 1 :", "Q1.", "Q.1", "Q 1 :", "1.", "1)", "1 -"
 *
 * Apres extraction, un reponse est :
 *   - "repondu = true" si texte non vide et non "N/A" / "NSP" / "?" / "-"
 *   - sinon "repondu = false"
 */

namespace App\Services\Document;

class QuestionnaireParser
{
    /**
     * Seuil pour considerer un document comme un questionnaire :
     * au moins N questions structurees detectees.
     */
    private const SEUIL_QUESTIONS_MIN = 3;

    /**
     * Heuristique : le document est-il un questionnaire ?
     */
    public function estQuestionnaire(string $texte): bool
    {
        $questions = $this->extraireQuestions($texte);

        return $this->validerSignatureQuestionnaire($questions);
    }

    /**
     * Analyse complete : retourne {is_questionnaire, nb_questions, nb_repondues, questions}.
     */
    public function analyser(string $texte): array
    {
        $questions = $this->extraireQuestions($texte);
        $nbTotal = count($questions);
        $nbRepondues = count(array_filter($questions, fn ($q) => $q['repondu']));

        return [
            'is_questionnaire' => $this->validerSignatureQuestionnaire($questions),
            'nb_questions' => $nbTotal,
            'nb_questions_repondues' => $nbRepondues,
            'questions_data' => $questions,
        ];
    }

    /**
     * Verifie qu'un document avec items numerotes est VRAIMENT un questionnaire.
     *
     * Un contrat (clauses "1. Objet", "2. Definitions"...) matche le pattern de
     * numerotation mais ne contient ni "?" ni marqueur "Reponse :". On exige
     * qu'au moins 30% des items aient une signature de Q/R reelle.
     */
    private function validerSignatureQuestionnaire(array $questions): bool
    {
        if (count($questions) < self::SEUIL_QUESTIONS_MIN) {
            return false;
        }

        $nbAvecSignature = 0;
        foreach ($questions as $q) {
            $bloc = $q['raw'] ?? (($q['question'] ?? '') . "\n" . ($q['reponse'] ?? ''));
            $aPointInterrogation = str_contains($q['question'] ?? '', '?');
            $aMarqueurReponse = (bool) preg_match('/\b(R[eé]ponse|Rep\.|R\s*[:\.])/iu', $bloc);
            if ($aPointInterrogation || $aMarqueurReponse) {
                $nbAvecSignature++;
            }
        }

        return ($nbAvecSignature / count($questions)) >= 0.30;
    }

    /**
     * Decoupe le texte en blocs Q/R.
     *
     * 2 modes de detection :
     *   1. Questions numerotees explicitement ("Question 1 :", "Q.1", "1.", "1)", ...)
     *   2. Questions "libres" : phrase se terminant par `?` suivie d'une zone de
     *      reponse (pointillés …, blancs, texte utilisateur).
     *
     * @return array<int, array{numero:int, question:string, reponse:string, repondu:bool, raw:string}>
     */
    public function extraireQuestions(string $texte): array
    {
        if (empty(trim($texte))) {
            return [];
        }

        $texte = $this->nettoyer($texte);

        // Mode 1 : questions numerotees
        $questions = $this->extraireNumerotees($texte);
        if (count($questions) >= self::SEUIL_QUESTIONS_MIN) {
            return $questions;
        }

        // Mode 2 : questions ouvertes terminees par ?
        return $this->extraireParPointInterrogation($texte);
    }

    /**
     * Mode 1 — Questions avec marqueur de numerotation explicite.
     */
    private function extraireNumerotees(string $texte): array
    {
        $pattern = '/(?:^|\n)\s*(?:Question\s+(\d+)|Q[\.\s]*(\d+)|(\d+)[\.\)\-]\s+)(?=[A-Z])/u';
        preg_match_all($pattern, $texte, $matches, PREG_OFFSET_CAPTURE);

        if (count($matches[0]) === 0) {
            return [];
        }

        $questions = [];
        foreach ($matches[0] as $i => $match) {
            $numero = (int) ($matches[1][$i][0] ?: $matches[2][$i][0] ?: $matches[3][$i][0]);
            $debut = $match[1];
            $fin = $matches[0][$i + 1][1] ?? mb_strlen($texte);
            $bloc = trim(mb_substr($texte, $debut, $fin - $debut));

            $blocSansMarqueur = preg_replace(
                '/^\s*(?:Question\s+\d+|Q[\.\s]*\d+|\d+[\.\)\-])\s*[\:\.]?\s*/u',
                '',
                $bloc
            );

            [$question, $reponse] = $this->separerQuestionReponse($blocSansMarqueur);

            $questions[] = [
                'numero' => $numero,
                'question' => $question,
                'reponse' => $reponse,
                'repondu' => $this->estReponseValide($reponse),
                'raw' => $bloc,
            ];
        }

        // Dedoublonner
        $vus = [];
        $filtrees = [];
        foreach ($questions as $q) {
            if (! isset($vus[$q['numero']])) {
                $vus[$q['numero']] = true;
                $filtrees[] = $q;
            }
        }

        return $filtrees;
    }

    /**
     * Mode 2 — Questions identifiees par leur "?" final.
     * On cherche toutes les phrases qui se terminent par ?, puis on capture ce qui
     * suit (jusqu'a la phrase ?-suivante ou la fin) comme reponse potentielle.
     *
     * Une zone composee uniquement de pointilles (…, ....., ----) est consideree
     * comme une reponse vide (non repondue).
     */
    private function extraireParPointInterrogation(string $texte): array
    {
        // Split en "segments" separes par le "?"
        // On trouve toutes les positions du "?"
        $positions = [];
        $offset = 0;
        while (($pos = mb_strpos($texte, '?', $offset)) !== false) {
            $positions[] = $pos;
            $offset = $pos + 1;
        }

        if (count($positions) < self::SEUIL_QUESTIONS_MIN) {
            return [];
        }

        $questions = [];
        $debutBloc = 0;
        foreach ($positions as $i => $posPoint) {
            // Extraire la question : du debut du bloc jusqu'au "?"
            $segmentQ = trim(mb_substr($texte, $debutBloc, $posPoint - $debutBloc + 1));

            // Retirer eventuellement le debut si on a saute une intro
            // (Heuristique : garder apres le dernier "\n\n" pour eviter d'inclure
            // les paragraphes precedents).
            $derniereCoupe = mb_strrpos($segmentQ, "\n\n");
            if ($derniereCoupe !== false) {
                $segmentQ = trim(mb_substr($segmentQ, $derniereCoupe + 2));
            }
            // Retirer les bullets ou tirets de debut de ligne
            $segmentQ = preg_replace('/^[\s\-•●▪◦]+/u', '', $segmentQ);

            // La question doit faire au moins 10 caracteres et contenir au moins 1 lettre
            if (mb_strlen($segmentQ) < 10 || ! preg_match('/\p{L}/u', $segmentQ)) {
                continue;
            }

            // La zone de reponse : apres le "?", jusqu'au prochain "?"
            $finReponse = $positions[$i + 1] ?? mb_strlen($texte);
            // Mais on coupe au "\n" qui precede la prochaine question (pour ne pas
            // inclure la question suivante dans la reponse).
            $zoneReponse = mb_substr($texte, $posPoint + 1, $finReponse - $posPoint - 1);
            // Retirer la potentielle prochaine question de la reponse
            $dernierSaut = mb_strrpos($zoneReponse, "\n");
            if ($dernierSaut !== false && $i + 1 < count($positions)) {
                // Si on a plus que 100 chars apres le dernier saut, c'est peut-etre la
                // prochaine question qui est collee — on coupe
                $zoneReponse = mb_substr($zoneReponse, 0, $dernierSaut);
            }

            // Nettoyer la reponse : enlever les pointilles, points, dashes, tirets
            $reponseCleaned = trim(preg_replace('/[…\.…]+/u', '', $zoneReponse));
            $reponseCleaned = preg_replace('/^[-_\*\s\.]+/u', '', $reponseCleaned);
            $reponseCleaned = preg_replace('/[\s\.…_\-]{3,}/u', ' ', $reponseCleaned);
            $reponseCleaned = trim($reponseCleaned);

            $questions[] = [
                'numero' => count($questions) + 1, // numerotation auto 1, 2, 3...
                'question' => $segmentQ,
                'reponse' => $reponseCleaned,
                'repondu' => $this->estReponseValide($reponseCleaned),
                'raw' => $segmentQ . ' [...]',
            ];

            $debutBloc = $finReponse;
        }

        return $questions;
    }

    /**
     * Nettoie les artefacts d'extraction PDF courants.
     */
    private function nettoyer(string $texte): string
    {
        // Decoder les entites HTML (venant d'extraction DOCX : &amp; &#039; etc.)
        $texte = html_entity_decode($texte, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Remplacer les multiples espaces/tabulations par un seul espace
        $texte = preg_replace('/[ \t]+/', ' ', $texte);
        // Retirer les marqueurs PAGE_BREAK inseres par le PDF extractor
        $texte = preg_replace('/\[PAGE_BREAK:\d+\]/', '', $texte);
        // Normaliser les retours ligne
        $texte = preg_replace('/\r\n|\r/', "\n", $texte);
        // Retirer les lignes quasi vides multiples
        $texte = preg_replace('/\n\s*\n\s*\n+/', "\n\n", $texte);

        return trim($texte);
    }

    /**
     * Separe le bloc en (question, reponse).
     * Heuristiques :
     *  - marqueur "Reponse :", "R :" ou "Rep."
     *  - OU : 1re ligne qui contient un "?" = question, le reste = reponse
     *  - OU : 1re ligne non vide = question, lignes suivantes = reponse
     */
    private function separerQuestionReponse(string $bloc): array
    {
        // Marqueur explicite de reponse - regex stricte pour eviter de matcher
        // des mots commencant par "re" (relation, responsable, regle...).
        // On exige : (a) "Réponse"/"Reponse" en mot complet OU "Rep." OU "R :"/"R."
        //            (b) suivi d'un separateur ":" ou "." obligatoire
        if (preg_match('/^(.*?)\s+(?:R[eé]ponse|Rep\.?)\s*[:\.]\s+(.*)$/isu', $bloc, $m)) {
            return [trim($m[1]), trim($m[2])];
        }
        // Cas alternatif : "R :" seul (R lettre isolee suivi de : ou .)
        if (preg_match('/^(.*?)\s+R\s*[:\.]\s+(.*)$/isu', $bloc, $m)) {
            return [trim($m[1]), trim($m[2])];
        }

        // Premiere ligne avec "?"
        $lignes = array_map('trim', preg_split('/\n/', $bloc));
        $lignes = array_values(array_filter($lignes, fn ($l) => $l !== ''));
        if (empty($lignes)) {
            return ['', ''];
        }

        foreach ($lignes as $idx => $ligne) {
            if (str_contains($ligne, '?')) {
                $question = implode(' ', array_slice($lignes, 0, $idx + 1));
                $reponse = implode(' ', array_slice($lignes, $idx + 1));

                return [trim($question), trim($reponse)];
            }
        }

        // Fallback : 1re ligne = question, reste = reponse
        $question = $lignes[0];
        $reponse = implode(' ', array_slice($lignes, 1));

        return [trim($question), trim($reponse)];
    }

    /**
     * Une reponse est-elle "valide" (c.-a-d. le client a effectivement repondu) ?
     */
    private function estReponseValide(string $reponse): bool
    {
        $reponse = trim($reponse);
        if ($reponse === '') {
            return false;
        }

        // Trop court pour etre une vraie reponse
        if (mb_strlen($reponse) < 3) {
            return false;
        }

        // Reponses "vides" courantes
        $motsVides = ['n/a', 'na', 'nsp', 'ne sait pas', 'rien', 'aucun', 'aucune', '-', '...', '?', 'a completer', 'a remplir'];
        $lower = mb_strtolower($reponse);
        foreach ($motsVides as $m) {
            if ($lower === $m || $lower === $m . '.') {
                return false;
            }
        }

        return true;
    }
}
