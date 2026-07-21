<?php

/**
 * Seeder ChartesSeeder — Charte IA initiale et charte sous-traitance.
 *
 * Publie 2 chartes v1.0 obligatoires que tout client doit signer :
 *   - charte_ia : engagements sur l'usage de l'IA et des donnees
 *   - charte_sous_traitance : clauses DPA entre le client et ASC
 */

namespace Database\Seeders;

use App\Models\Charte;
use Illuminate\Database\Seeder;

class ChartesSeeder extends Seeder
{
    public function run(): void
    {
        $this->publierCharteIA();
        $this->publierCharteSousTraitance();
    }

    /**
     * Charte IA & Tiers — basee sur le document AS Consulting
     * « DOCUMENT 03 - LA CHARTE IA & TIERS.docx » (standards AI Act / DORA 2026).
     *
     * Structure : Note de service + 4 sections de regles + coupon d'engagement
     * detachable a retourner aux RH.
     */
    private function publierCharteIA(): void
    {
        $contenu = <<<'HTML'
<h1>La Charte IA &amp; Tiers</h1>
<p><em>Mise a jour avec les standards internationaux 2026 (AI Act / DORA)</em></p>

<div class="charte-entete">
  <p><strong>NOTE DE SERVICE INTERNE N&deg;</strong> [2026-00X]</p>
  <p><strong>OBJET :</strong> Gouvernance de l'Intelligence Artificielle &amp; Securite Tiers</p>
  <p><strong>REFERENCE :</strong> Loi 2013-450 / Standards internationaux (AI Act)</p>
  <p><strong>DATE :</strong> [DATE DU JOUR]</p>
</div>

<h2>1. Contexte : le &laquo; permis de construire &raquo; numerique</h2>
<p>L'usage non controle de l'IA (<em>Shadow AI</em>) ne constitue pas seulement un risque technique, mais une dette reglementaire majeure.</p>
<p>Les standards internationaux sanctionnent desormais ces derives jusqu'a <strong>7 % du chiffre d'affaires</strong> (AI Act).</p>
<p>Pour preserver notre eligibilite aupres de nos grands donneurs d'ordres (banques, Etat, multinationales), <strong>[NOM DE VOTRE ENTREPRISE]</strong> durcit ses regles de securite.</p>

<h2>2. Regles internes (pour les salaries)</h2>

<h3>Regle n&deg;1 - Tolerance zero sur l'injection</h3>
<p>Il est <strong>strictement interdit</strong> de saisir ou telecharger dans une IA publique (ChatGPT, DeepL, etc.) :</p>
<ul>
  <li>Donnees personnelles (clients, RH).</li>
  <li>Secrets d'affaires (contrats, strategie, code).</li>
</ul>
<p><strong>Sanction :</strong> faute grave immediate.</p>

<h3>Regle n&deg;2 - Anonymisation prealable</h3>
<p>Tout usage d'IA pour assistance a la redaction necessite une <strong>anonymisation totale</strong> des donnees avant la saisie (remplacement des noms / montants par des variables X / Y).</p>

<h3>Regle n&deg;3 - Vigilance &laquo; deepfake &raquo; (anti-fraude)</h3>
<p>Aucun virement ou transfert de donnees sensibles ne peut etre execute sur la base d'un appel video ou vocal <strong>sans contre-appel de verification</strong>, pour parer aux usurpations d'identite par IA.</p>

<h2>3. Regles externes (pour les prestataires &amp; fournisseurs)</h2>

<h3>Regle n&deg;4 - Interdiction de sous-traitance IA non declaree</h3>
<p>Il est interdit de transmettre des donnees de l'entreprise a un prestataire (avocat, comptable, marketing, dev) <strong>sans avoir obtenu par ecrit la garantie</strong> qu'il n'utilise pas d'IA publique pour traiter nos dossiers.</p>
<p><em>Exemple : votre agence de communication ne doit pas mettre votre fichier clients dans une IA pour &laquo; analyser les tendances &raquo; sans contrat specifique.</em></p>
<p><strong>Action :</strong> tout contrat fournisseur doit desormais inclure la clause de &laquo; Transparence IA &raquo;.</p>

<h2>4. Validation et responsabilite</h2>
<p>L'IA est un outil, <strong>pas un auteur</strong>. Le collaborateur reste seul responsable de la veracite et de la securite du contenu produit. L'argument &laquo; c'est l'IA qui s'est trompee &raquo; est irrecevable.</p>

<p style="margin-top:24px"><em>Pour la Direction Generale,</em><br>
<strong>[NOM DU DIRIGEANT]</strong> <em>(Signature)</em></p>

<hr>

<h2>Coupon de validation et d'engagement</h2>
<p><em>A retourner obligatoirement aux RH</em></p>
<p>Je soussigne(e), <strong>........................................................................</strong>,</p>
<p>occupant le poste de <strong>..................................................................</strong>,</p>
<p>certifie avoir <strong>recu, lu et compris</strong> la Note de Service N&deg; [2026-00X] relative a la Gouvernance IA.</p>
<p>Je m'engage a l'appliquer strictement. Je comprends que toute utilisation d'une IA publique avec des donnees de l'entreprise constitue une <strong>faute grave</strong> entrainant mon licenciement immediat et engageant ma responsabilite civile et penale personnelle, dechargeant ainsi la Direction.</p>
<p><strong>Date :</strong> ..... / ..... / 2026</p>
<p><strong>Mention manuscrite :</strong> ecrire <em>&laquo; Lu et approuve, bon pour engagement penal et disciplinaire &raquo;</em></p>
<p>..............................................................................................................................</p>
<p><strong>Signature du salarie :</strong></p>

<p style="margin-top:32px; font-size:11px; color:#666"><em>Ce document est la propriete intellectuelle de AS Consulting. Reproduction interdite.</em></p>
HTML;

        $hash = Charte::calculerHash($contenu);

        Charte::updateOrCreate(
            ['type' => 'charte_ia', 'version' => '2.0'],
            [
                'titre' => 'La Charte IA & Tiers',
                'contenu_html' => $contenu,
                'hash_contenu' => $hash,
                'active' => true,
                'obligatoire' => true,
                'publiee_le' => now(),
            ]
        );

        // Desactive la version 1.0 si elle existe encore (les anciennes signatures
        // restent valides mais cibleront un document marque non-actif).
        Charte::where('type', 'charte_ia')
            ->where('version', '1.0')
            ->update(['active' => false]);
    }

    private function publierCharteSousTraitance(): void
    {
        $contenu = <<<'HTML'
<h1>Charte de sous-traitance (DPA)</h1>

<p>Accord de sous-traitance des donnees au sens de l'Article 20 de la Loi n° 2013-450.</p>

<h2>Article 1 - Parties</h2>
<p>Le <strong>Responsable de traitement</strong> est l'Entreprise signataire. Le <strong>Sous-traitant</strong> est AS Consulting agissant pour l'execution des prestations de conseil et d'analyse de conformite.</p>

<h2>Article 2 - Finalites</h2>
<p>Le sous-traitant traite les donnees exclusivement pour realiser les prestations de conseil, d'analyse d'ecarts et de generation de livrables commandees par le Client.</p>

<h2>Article 3 - Categories de donnees</h2>
<p>Donnees d'identite (nom, prenom, email, telephone), donnees professionnelles (poste, service), contenus documentaires fournis volontairement par le Client.</p>

<h2>Article 4 - Obligations du sous-traitant</h2>
<ul>
  <li>Traiter les donnees uniquement sur instruction documentee du responsable de traitement.</li>
  <li>Garantir la confidentialite par signature d'engagements individuels des employes.</li>
  <li>Mettre en oeuvre les mesures techniques appropriees : chiffrement, pseudonymisation, controle des acces.</li>
  <li>Notifier toute violation de donnees dans les 24 heures.</li>
  <li>Supprimer ou restituer les donnees a la fin de la prestation au choix du responsable.</li>
</ul>

<h2>Article 5 - Sous-traitance ulterieure</h2>
<p>Aucune sous-traitance ulterieure sans autorisation ecrite prealable du responsable de traitement.</p>

<h2>Article 6 - Transfert hors CEDEAO</h2>
<p>Aucun transfert de donnees hors de la zone CEDEAO ne sera effectue. L'infrastructure d'hebergement est localisee en Cote d'Ivoire.</p>
HTML;

        $hash = Charte::calculerHash($contenu);

        Charte::updateOrCreate(
            ['type' => 'charte_sous_traitance', 'version' => '1.0'],
            [
                'titre' => 'Charte de sous-traitance (DPA)',
                'contenu_html' => $contenu,
                'hash_contenu' => $hash,
                'active' => true,
                'obligatoire' => true,
                'publiee_le' => now(),
            ]
        );
    }
}
