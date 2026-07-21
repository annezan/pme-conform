<?php

/**
 * Service RegistreKycGenerator — Genere dynamiquement le registre
 * des traitements au format .docx a partir des traitements valides
 * d'un client.
 *
 * Chaque generation fige :
 *   - les IDs et numeros de revision des traitements inclus (snapshot)
 *   - un hash SHA-256 du fichier produit (anti-tampering)
 */

namespace App\Services\Registre;

use App\Models\Client;
use App\Models\RegistreKyc;
use App\Models\Traitement;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Language;

class RegistreKycGenerator
{
    public function generer(Client $client, User $initiateur): RegistreKyc
    {
        $traitements = $client->traitements()
            ->valides()
            ->with(['saisiPar:id,nom,prenom', 'validePar:id,nom,prenom'])
            ->orderBy('reference')
            ->get();

        $snapshot = $traitements->map(fn (Traitement $t) => [
            'id' => $t->id,
            'reference' => $t->reference,
            'revision_id' => $t->revisions()->latest('id')->value('id'),
            'valide_at' => $t->valide_at?->toIso8601String(),
        ])->toArray();

        $registre = RegistreKyc::create([
            'client_id' => $client->id,
            'genere_par' => $initiateur->id,
            'reference' => RegistreKyc::genererReference(),
            'nb_traitements' => $traitements->count(),
            'snapshot_traitements' => $snapshot,
            'fichier_path' => '', // sera rempli apres generation
            'hash_fichier' => '',
            'format' => 'docx',
            'statut_generation' => 'en_cours',
        ]);

        try {
            $cheminRelatif = $this->construireDocx($client, $traitements, $registre);
            $cheminAbsolu = Storage::disk('local')->path($cheminRelatif);
            $hash = hash_file('sha256', $cheminAbsolu);

            $registre->update([
                'fichier_path' => $cheminRelatif,
                'hash_fichier' => $hash,
                'statut_generation' => 'termine',
            ]);
        } catch (\Throwable $e) {
            $registre->update([
                'statut_generation' => 'erreur',
                'erreur_message' => mb_substr($e->getMessage(), 0, 500),
            ]);
            throw $e;
        }

        return $registre->fresh();
    }

    private function construireDocx(Client $client, Collection $traitements, RegistreKyc $registre): string
    {
        $phpWord = new PhpWord();
        $phpWord->getSettings()->setThemeFontLang(new Language(Language::FR_FR));
        $phpWord->setDefaultFontName('Calibri');
        $phpWord->setDefaultFontSize(11);

        $phpWord->addTitleStyle(1, ['size' => 22, 'bold' => true, 'color' => '1A5490'], ['spaceBefore' => 400, 'spaceAfter' => 200]);
        $phpWord->addTitleStyle(2, ['size' => 16, 'bold' => true, 'color' => '2C3E50'], ['spaceBefore' => 300, 'spaceAfter' => 150]);
        $phpWord->addTitleStyle(3, ['size' => 13, 'bold' => true, 'color' => '34495E'], ['spaceBefore' => 200, 'spaceAfter' => 100]);
        $phpWord->addFontStyle('small', ['size' => 9, 'color' => '7F8C8D', 'italic' => true]);
        $phpWord->addFontStyle('note', ['color' => '1A5490', 'italic' => true]);
        $phpWord->addParagraphStyle('centre', ['alignment' => Jc::CENTER]);

        // ========== COUVERTURE ==========
        $s = $phpWord->addSection();
        $s->addTextBreak(5);
        $s->addText('REGISTRE DES TRAITEMENTS', ['size' => 28, 'bold' => true, 'color' => '1A5490'], 'centre');
        $s->addText('DE DONNEES A CARACTERE PERSONNEL', ['size' => 16, 'color' => '2C3E50'], 'centre');
        $s->addTextBreak(2);
        $s->addText('Article 30 — Loi n° 2013-450 (ARTCI)', 'small', 'centre');
        $s->addTextBreak(4);
        $s->addText($client->raison_sociale ?? '', ['size' => 22, 'bold' => true], 'centre');
        if ($client->sigle) {
            $s->addText($client->sigle, ['size' => 14, 'italic' => true], 'centre');
        }
        $s->addTextBreak(2);

        $infos = [
            'Reference' => $registre->reference,
            'Date de generation' => now()->format('d/m/Y H:i'),
            'Nombre de traitements' => $traitements->count(),
        ];
        if ($client->secteur_activite) {
            $infos['Secteur d\'activite'] = $client->secteur_activite;
        }
        if ($client->numero_rccm) {
            $infos['RCCM'] = $client->numero_rccm;
        }
        if ($client->adresse) {
            $infos['Adresse'] = $client->adresse;
        }

        foreach ($infos as $k => $v) {
            $s->addText("{$k} : {$v}", ['size' => 12], 'centre');
        }

        $s->addTextBreak(4);
        $s->addText('Document confidentiel — Reserve a l\'entreprise et aux autorites competentes', 'small', 'centre');
        $s->addPageBreak();

        // ========== SOMMAIRE ==========
        $s->addTitle('Sommaire', 1);
        foreach ($traitements as $i => $t) {
            $num = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
            $s->addText("{$num}. {$t->reference} — {$t->nom}", ['size' => 11], ['spaceAfter' => 60]);
        }

        // ========== UN SLIDE PAR TRAITEMENT ==========
        foreach ($traitements as $i => $t) {
            $s = $phpWord->addSection();
            $s->addTitle(($i + 1) . '. ' . $t->nom, 1);
            $s->addText($t->reference, 'small');
            $s->addTextBreak(1);

            $this->ligneMeta($s, 'Statut', 'Valide le ' . ($t->valide_at?->format('d/m/Y') ?? '-'));
            $this->ligneMeta($s, 'Saisi par', $this->formatUser($t->saisiPar));
            $this->ligneMeta($s, 'Valide par', $this->formatUser($t->validePar));

            if ($t->responsable_traitement_nom) {
                $this->ligneMeta($s, 'Responsable du traitement', $t->responsable_traitement_nom);
            }

            $s->addTitle('Finalite', 3);
            $s->addText($t->finalite_principale ?? '-');
            if (! empty($t->finalites_secondaires)) {
                foreach ($t->finalites_secondaires as $fs) {
                    $s->addText('• ' . $fs, null, ['indentation' => ['left' => 360]]);
                }
            }

            $s->addTitle('Bases legales', 3);
            $s->addText(implode(', ', array_map('ucfirst', $t->bases_legales ?? [])));

            $s->addTitle('Categories de personnes concernees', 3);
            $s->addText(implode(', ', $t->categories_personnes ?? []));

            $s->addTitle('Categories de donnees', 3);
            $s->addText(implode(', ', $t->categories_donnees ?? []));

            if ($t->donnees_sensibles) {
                $s->addText('/!\ Ce traitement porte sur des donnees sensibles.', ['bold' => true, 'color' => 'C0392B']);
            }

            $s->addTitle('Durees de conservation', 3);
            $s->addText(sprintf(
                'Conservation active : %s mois  |  Archivage : %s mois',
                $t->duree_conservation_active_mois ?? '-',
                $t->duree_archivage_mois ?? '-'
            ));
            if ($t->justification_duree) {
                $s->addText($t->justification_duree, ['italic' => true, 'size' => 10]);
            }

            if (! empty($t->destinataires_internes) || ! empty($t->destinataires_externes)) {
                $s->addTitle('Destinataires', 3);
                if (! empty($t->destinataires_internes)) {
                    $s->addText('Internes : ' . $this->formatListe($t->destinataires_internes));
                }
                if (! empty($t->destinataires_externes)) {
                    $s->addText('Externes : ' . $this->formatListe($t->destinataires_externes));
                }
            }

            if ($t->transfert_hors_cedeao) {
                $s->addTitle('Transfert hors CEDEAO', 3);
                $s->addText('Base juridique : ' . ($t->base_transfert ?? 'non precise'));
                if (! empty($t->pays_destinataires)) {
                    $s->addText('Pays : ' . implode(', ', $t->pays_destinataires));
                }
            }

            if (! empty($t->mesures_techniques) || ! empty($t->mesures_organisationnelles)) {
                $s->addTitle('Mesures de securite', 3);
                if (! empty($t->mesures_techniques)) {
                    $s->addText('Techniques : ' . implode(', ', $t->mesures_techniques));
                }
                if (! empty($t->mesures_organisationnelles)) {
                    $s->addText('Organisationnelles : ' . implode(', ', $t->mesures_organisationnelles));
                }
            }

            if (! empty($t->sous_traitants)) {
                $s->addTitle('Sous-traitants', 3);
                $s->addText($this->formatListe($t->sous_traitants));
            }
        }

        // ========== FIN ==========
        $s->addTextBreak(2);
        $s->addText('— Fin du registre —', 'small', 'centre');
        $s->addText('Ce registre a ete genere automatiquement par la plateforme PME-CONFORM. Reference : ' . $registre->reference, 'small', 'centre');
        $s->addText('Empreinte SHA-256 : (calculee apres sauvegarde)', 'small', 'centre');

        // Sauvegarde
        $dossier = 'registres-kyc';
        $nomFichier = sprintf('registre-%s-%s.docx', $registre->reference, now()->format('Ymd-His'));
        $cheminRelatif = $dossier . '/' . $nomFichier;
        $cheminAbsolu = Storage::disk('local')->path($cheminRelatif);

        if (! is_dir(dirname($cheminAbsolu))) {
            mkdir(dirname($cheminAbsolu), 0775, true);
        }

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($cheminAbsolu);

        return $cheminRelatif;
    }

    private function ligneMeta($section, string $label, string $valeur): void
    {
        $section->addText("{$label} : {$valeur}", ['size' => 10, 'color' => '5D6D7E']);
    }

    private function formatUser($user): string
    {
        if (! $user) {
            return '-';
        }

        return trim(($user->prenom ?? '') . ' ' . ($user->nom ?? ''));
    }

    private function formatListe(array $items): string
    {
        if (empty($items)) {
            return '-';
        }

        $strings = array_map(function ($item) {
            if (is_string($item)) {
                return $item;
            }
            if (is_array($item)) {
                return implode(' — ', array_filter($item, fn ($v) => is_scalar($v)));
            }

            return (string) $item;
        }, $items);

        return implode(' ; ', $strings);
    }
}
