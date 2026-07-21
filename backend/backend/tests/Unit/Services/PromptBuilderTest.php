<?php

namespace Tests\Unit\Services;

use App\Models\Agent;
use App\Services\Orchestrator\PromptBuilder;
use Tests\TestCase;

class PromptBuilderTest extends TestCase
{
    private PromptBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new PromptBuilder();
    }

    public function test_prompt_systeme_est_premier_message(): void
    {
        $agent = new Agent(['prompt_systeme' => 'Tu es un assistant.']);
        $messages = $this->builder->construire($agent, 'Bonjour');

        $this->assertEquals('system', $messages[0]['role']);
        $this->assertEquals('Tu es un assistant.', $messages[0]['content']);
    }

    public function test_requete_utilisateur_est_dernier_message(): void
    {
        $agent = new Agent(['prompt_systeme' => 'System.']);
        $messages = $this->builder->construire($agent, 'Ma question');

        $dernier = end($messages);
        $this->assertEquals('user', $dernier['role']);
        $this->assertEquals('Ma question', $dernier['content']);
    }

    public function test_historique_est_inclus_dans_bon_ordre(): void
    {
        $agent = new Agent(['prompt_systeme' => 'System.']);
        $historique = [
            ['role' => 'user', 'content' => 'Premier message'],
            ['role' => 'assistant', 'content' => 'Premiere reponse'],
        ];

        $messages = $this->builder->construire($agent, 'Deuxieme message', $historique);

        $this->assertCount(4, $messages); // system + 2 historique + user
        $this->assertEquals('user', $messages[1]['role']);
        $this->assertEquals('Premier message', $messages[1]['content']);
        $this->assertEquals('assistant', $messages[2]['role']);
    }

    public function test_chunks_rag_injectes_comme_system_message(): void
    {
        $agent = new Agent(['prompt_systeme' => 'System.']);
        $chunks = [
            ['contenu' => 'Extrait du document.', 'document_titre' => 'Rapport 2024', 'page' => 3],
        ];

        $messages = $this->builder->construire($agent, 'Question', [], $chunks);

        $this->assertCount(3, $messages); // system + rag system + user
        $this->assertEquals('system', $messages[1]['role']);
        $this->assertStringContainsString('Extrait du document.', $messages[1]['content']);
        $this->assertStringContainsString('Rapport 2024', $messages[1]['content']);
        $this->assertStringContainsString('page 3', $messages[1]['content']);
    }

    public function test_sans_rag_pas_de_message_supplementaire(): void
    {
        $agent = new Agent(['prompt_systeme' => 'System.']);
        $messages = $this->builder->construire($agent, 'Question');

        $this->assertCount(2, $messages); // system + user
    }
}
