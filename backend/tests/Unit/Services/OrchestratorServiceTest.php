<?php

namespace Tests\Unit\Services;

use App\Contracts\LLMConnectorInterface;
use App\Contracts\RetrievalInterface;
use App\Events\AgentReponseGeneree;
use App\Events\AgentRequeteRecue;
use App\Models\Agent;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\Orchestrator\OrchestratorService;
use App\Services\Orchestrator\PromptBuilder;
use App\Services\Security\PseudonymizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OrchestratorServiceTest extends TestCase
{
    use RefreshDatabase;

    private function creerOrchestrator(
        ?LLMConnectorInterface $llm = null,
        ?PseudonymizationService $pseudo = null,
        ?RetrievalInterface $retrieval = null,
    ): OrchestratorService {
        return new OrchestratorService(
            llm: $llm ?? $this->mockLlm(),
            pseudonymisation: $pseudo ?? new PseudonymizationService(),
            audit: $this->createMock(AuditService::class),
            retrieval: $retrieval ?? $this->mockRetrieval(),
            promptBuilder: new PromptBuilder(),
        );
    }

    private function mockLlm(): LLMConnectorInterface
    {
        $mock = $this->createMock(LLMConnectorInterface::class);
        $mock->method('completer')->willReturn([
            'content' => 'Reponse du LLM.',
            'tokens_entree' => 100,
            'tokens_sortie' => 50,
            'duree_ms' => 1500,
            'modele' => 'llama3.2',
        ]);

        return $mock;
    }

    private function mockRetrieval(): RetrievalInterface
    {
        $mock = $this->createMock(RetrievalInterface::class);
        $mock->method('rechercher')->willReturn([]);

        return $mock;
    }

    public function test_traiter_sauve_messages_user_et_assistant(): void
    {
        Event::fake();
        $user = User::factory()->create(['is_active' => true]);
        $this->actingAs($user);

        $agent = Agent::factory()->create(['type' => 'conversationnel']);
        $orchestrator = $this->creerOrchestrator();

        $resultat = $orchestrator->traiter($agent, 'Bonjour');

        $this->assertNotNull($resultat['conversation']);
        $this->assertEquals('assistant', $resultat['message']->role);
        $this->assertEquals('Reponse du LLM.', $resultat['message']->contenu);

        // Verifier que 2 messages existent (user + assistant)
        $this->assertEquals(2, $resultat['conversation']->messages()->count());
    }

    public function test_traiter_fire_evenements(): void
    {
        Event::fake([AgentRequeteRecue::class, AgentReponseGeneree::class]);
        $user = User::factory()->create(['is_active' => true]);
        $this->actingAs($user);

        $agent = Agent::factory()->create(['type' => 'conversationnel']);
        $orchestrator = $this->creerOrchestrator();

        $orchestrator->traiter($agent, 'Test');

        Event::assertDispatched(AgentRequeteRecue::class);
        Event::assertDispatched(AgentReponseGeneree::class);
    }

    public function test_traiter_utilise_rag_pour_agent_analytique(): void
    {
        Event::fake();
        $user = User::factory()->create(['is_active' => true]);
        $this->actingAs($user);

        $agent = Agent::factory()->create(['type' => 'analytique']);

        $retrieval = $this->createMock(RetrievalInterface::class);
        $retrieval->expects($this->once())
            ->method('rechercher')
            ->willReturn([
                ['contenu' => 'Extrait.', 'document_id' => 1, 'document_titre' => 'Doc', 'score' => 0.9, 'page' => 1],
            ]);

        $orchestrator = $this->creerOrchestrator(retrieval: $retrieval);
        $resultat = $orchestrator->traiter($agent, 'Analyse ce document');

        $this->assertNotEmpty($resultat['sources']);
    }

    public function test_traiter_pas_de_rag_pour_chatbot(): void
    {
        Event::fake();
        $user = User::factory()->create(['is_active' => true]);
        $this->actingAs($user);

        $agent = Agent::factory()->create(['type' => 'conversationnel']);

        $retrieval = $this->createMock(RetrievalInterface::class);
        $retrieval->expects($this->never())->method('rechercher');

        $orchestrator = $this->creerOrchestrator(retrieval: $retrieval);
        $resultat = $orchestrator->traiter($agent, 'Bonjour');

        $this->assertEmpty($resultat['sources']);
    }
}
