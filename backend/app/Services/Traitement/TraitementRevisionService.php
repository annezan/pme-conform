<?php

/**
 * Service TraitementRevisionService — Gere l'historique des modifications
 * d'un traitement.
 *
 * A chaque update pertinent (ou validation), on capture un snapshot JSON
 * complet du traitement dans traitement_revisions. Permet :
 *   - audit metier (qui a change quoi et quand)
 *   - snapshot fige dans les registres KYC (on reference revision_id pour
 *     garantir l'immuabilite du registre)
 *   - timeline affichee cote UI sur la page detail
 */

namespace App\Services\Traitement;

use App\Models\Traitement;
use App\Models\TraitementRevision;
use App\Models\User;

class TraitementRevisionService
{
    /**
     * Cree une nouvelle revision a partir de l'etat courant du traitement.
     */
    public function capturer(Traitement $traitement, User $auteur, ?string $commentaire = null): TraitementRevision
    {
        $snapshot = $traitement->fresh()->only([
            'id', 'client_id', 'reference', 'nom', 'statut',
            'finalite_principale', 'finalites_secondaires', 'bases_legales',
            'categories_personnes', 'categories_donnees',
            'donnees_sensibles', 'donnees_sensibles_types',
            'duree_conservation_active_mois', 'duree_archivage_mois', 'justification_duree',
            'destinataires_internes', 'destinataires_externes',
            'transfert_hors_cedeao', 'pays_destinataires', 'base_transfert',
            'mesures_techniques', 'mesures_organisationnelles',
            'responsable_traitement_nom', 'sous_traitants',
            'valide_par', 'valide_at',
        ]);

        return TraitementRevision::create([
            'traitement_id' => $traitement->id,
            'modifie_par' => $auteur->id,
            'snapshot' => $snapshot,
            'commentaire' => $commentaire,
            'created_at' => now(),
        ]);
    }

    /**
     * Construit une timeline propre pour l'UI : liste des revisions
     * avec date, auteur, et delta detecte vs revision precedente.
     */
    public function timeline(Traitement $traitement): array
    {
        $revisions = $traitement->revisions()
            ->with('auteur:id,nom,prenom')
            ->orderBy('created_at')
            ->get();

        $resultat = [];
        $precedent = null;

        foreach ($revisions as $rev) {
            $changements = $precedent ? $this->detecterChangements($precedent->snapshot, $rev->snapshot) : [];
            $resultat[] = [
                'id' => $rev->id,
                'date' => $rev->created_at?->toIso8601String(),
                'auteur' => $rev->auteur ? [
                    'id' => $rev->auteur->id,
                    'nom_complet' => trim(($rev->auteur->prenom ?? '') . ' ' . ($rev->auteur->nom ?? '')),
                ] : null,
                'commentaire' => $rev->commentaire,
                'changements' => $changements,
                'snapshot' => $rev->snapshot,
            ];
            $precedent = $rev;
        }

        return array_reverse($resultat); // plus recent en haut
    }

    /**
     * Detecte les champs dont la valeur a change entre deux snapshots.
     */
    private function detecterChangements(array $avant, array $apres): array
    {
        $champsSuivis = [
            'nom' => 'Nom du traitement',
            'statut' => 'Statut',
            'finalite_principale' => 'Finalite principale',
            'bases_legales' => 'Bases legales',
            'categories_personnes' => 'Categories de personnes',
            'categories_donnees' => 'Categories de donnees',
            'donnees_sensibles' => 'Donnees sensibles',
            'duree_conservation_active_mois' => 'Duree de conservation active',
            'duree_archivage_mois' => 'Duree d\'archivage',
            'transfert_hors_cedeao' => 'Transfert hors CEDEAO',
            'mesures_techniques' => 'Mesures techniques',
            'mesures_organisationnelles' => 'Mesures organisationnelles',
            'responsable_traitement_nom' => 'Responsable du traitement',
        ];

        $changes = [];
        foreach ($champsSuivis as $champ => $label) {
            $av = $avant[$champ] ?? null;
            $ap = $apres[$champ] ?? null;
            if ($av !== $ap) {
                $changes[] = [
                    'champ' => $champ,
                    'label' => $label,
                    'avant' => $av,
                    'apres' => $ap,
                ];
            }
        }

        return $changes;
    }
}
