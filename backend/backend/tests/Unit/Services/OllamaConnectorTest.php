<?php

/**
 * Tests unitaires du connecteur LLM Ollama.
 *
 * Utilise Http::fake() pour simuler les réponses du serveur Ollama
 * sans nécessiter un serveur local actif.
 */

namespace Tests\Unit\Services;

use App\Contracts\LLMConnectorInterface;
use App\Services\LLM\OllamaConnector;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OllamaConnectorTest extends TestCase
{
    private OllamaConnector $connector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connector = new OllamaConnector();
    }

    public function test_completer_retourne_reponse_formatee(): void
    {
        Http::fake([
            '*/api/chat' => Http::response([
                'message' => ['content' => 'Bonjour, je suis votre assistant.'],
                'prompt_eval_count' => 25,
                'eval_count' => 12,
                'model' => 'llama3.2',
            ], 200),
        ]);

        $resultat = $this->connector->completer([
            ['role' => 'user', 'content' => 'Bonjour'],
        ]);

        $this->assertArrayHasKey('content', $resultat);
        $this->assertArrayHasKey('tokens_entree', $resultat);
        $this->assertArrayHasKey('tokens_sortie', $resultat);
        $this->assertArrayHasKey('duree_ms', $resultat);
        $this->assertArrayHasKey('modele', $resultat);

        $this->assertEquals('Bonjour, je suis votre assistant.', $resultat['content']);
        $this->assertEquals(25, $resultat['tokens_entree']);
        $this->assertEquals(12, $resultat['tokens_sortie']);
    }

    public function test_completer_lance_exception_sur_erreur(): void
    {
        Http::fake([
            '*/api/chat' => Http::response('Model not found', 404),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Erreur Ollama/');

        $this->connector->completer([
            ['role' => 'user', 'content' => 'Test'],
        ]);
    }

    public function test_generer_embedding_retourne_vecteur(): void
    {
        $fakeEmbedding = array_fill(0, 10, 0.5);

        Http::fake([
            '*/api/embed' => Http::response([
                'embeddings' => [$fakeEmbedding],
            ], 200),
        ]);

        $resultat = $this->connector->genererEmbedding('Texte de test');

        $this->assertIsArray($resultat);
        $this->assertCount(10, $resultat);
    }

    public function test_est_disponible_retourne_true_si_serveur_actif(): void
    {
        Http::fake([
            '*/api/tags' => Http::response(['models' => []], 200),
        ]);

        $this->assertTrue($this->connector->estDisponible());
    }

    public function test_est_disponible_retourne_false_si_serveur_inactif(): void
    {
        Http::fake([
            '*/api/tags' => Http::response('', 500),
        ]);

        $this->assertFalse($this->connector->estDisponible());
    }

    public function test_lister_modeles_retourne_liste(): void
    {
        Http::fake([
            '*/api/tags' => Http::response([
                'models' => [
                    ['name' => 'llama3.2', 'size' => 4000000000],
                    ['name' => 'mistral', 'size' => 7000000000],
                ],
            ], 200),
        ]);

        $modeles = $this->connector->listerModeles();

        $this->assertCount(2, $modeles);
        $this->assertEquals('llama3.2', $modeles[0]['nom']);
        $this->assertEquals('mistral', $modeles[1]['nom']);
    }

    public function test_interface_est_correctement_liee(): void
    {
        $instance = app(LLMConnectorInterface::class);
        $this->assertInstanceOf(OllamaConnector::class, $instance);
    }
}
