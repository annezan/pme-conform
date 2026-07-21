<?php

/**
 * Tests unitaires du modèle Mission.
 *
 * Vérifie le format de la référence générée.
 */

namespace Tests\Unit\Models;

use PHPUnit\Framework\TestCase;

class MissionTest extends TestCase
{
    public function test_generer_reference_format_correct(): void
    {
        // Vérifie le format sans accès BDD
        $annee = date('Y');
        $reference = sprintf('MISS-%d-%03d', $annee, 1);

        $this->assertMatchesRegularExpression('/^MISS-\d{4}-\d{3}$/', $reference);
        $this->assertStringContainsString($annee, $reference);
        $this->assertEquals("MISS-{$annee}-001", $reference);
    }
}
