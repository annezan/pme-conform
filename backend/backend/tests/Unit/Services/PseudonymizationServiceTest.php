<?php

/**
 * Tests unitaires du service de pseudonymisation.
 *
 * Vérifie que les données personnelles sont correctement
 * remplacées par des tokens et restaurées.
 */

namespace Tests\Unit\Services;

use App\Services\Security\PseudonymizationService;
use Tests\TestCase;

class PseudonymizationServiceTest extends TestCase
{
    private PseudonymizationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PseudonymizationService();
    }

    public function test_pseudonymise_emails(): void
    {
        $texte = 'Contactez jean.dupont@example.com pour plus d\'informations.';
        $resultat = $this->service->pseudonymiser($texte);

        $this->assertStringNotContainsString('jean.dupont@example.com', $resultat);
        $this->assertStringContainsString('[EMAIL_1]', $resultat);
    }

    public function test_pseudonymise_telephones(): void
    {
        $texte = 'Appelez le +225 07 08 09 10 11 pour confirmer.';
        $resultat = $this->service->pseudonymiser($texte);

        $this->assertStringNotContainsString('+225 07 08 09 10 11', $resultat);
        $this->assertStringContainsString('[TEL_', $resultat);
    }

    public function test_depseudonymise_restaure_valeurs(): void
    {
        $texte = 'Email: contact@test.com, Tel: +225 01 02 03 04 05';
        $pseudonymise = $this->service->pseudonymiser($texte);
        $restaure = $this->service->depseudonymiser($pseudonymise);

        $this->assertStringContainsString('contact@test.com', $restaure);
    }

    public function test_meme_valeur_produit_meme_token(): void
    {
        $texte = 'Email: a@b.com et encore a@b.com ici.';
        $resultat = $this->service->pseudonymiser($texte);

        // Le même email doit produire le même token
        preg_match_all('/\[EMAIL_\d+\]/', $resultat, $matches);
        $this->assertCount(2, $matches[0]);
        $this->assertEquals($matches[0][0], $matches[0][1]);
    }

    public function test_reinitialiser_vide_le_mapping(): void
    {
        $this->service->pseudonymiser('test@email.com');
        $this->assertNotEmpty($this->service->getMapping());

        $this->service->reinitialiser();
        $this->assertEmpty($this->service->getMapping());
    }

    public function test_texte_sans_donnees_personnelles_inchange(): void
    {
        $texte = 'Ceci est un texte sans données personnelles identifiables.';
        $resultat = $this->service->pseudonymiser($texte);

        $this->assertEquals($texte, $resultat);
    }
}
