<?php

/**
 * Service OllamaConnector — Connecteur vers le serveur Ollama local.
 *
 * Gère toutes les communications avec le LLM local :
 * - Complétion de texte (synchrone et streaming)
 * - Génération d'embeddings pour le RAG
 * - Vérification de disponibilité
 *
 * IMPORTANT : Aucune requête ne sort du réseau local.
 */

namespace App\Services\LLM;

use App\Contracts\LLMConnectorInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaConnector implements LLMConnectorInterface
{
    private string $baseUrl;
    private string $defaultModel;
    private string $embeddingModel;
    private int $timeout;
    private int $maxTokens;

    public function __construct()
    {
        $this->baseUrl = config('services.ollama.host', 'http://127.0.0.1:11434');
        $this->defaultModel = config('services.ollama.model', 'llama3.2');
        $this->embeddingModel = config('services.ollama.embedding_model', 'llama3.2');
        $this->timeout = (int) config('services.ollama.timeout', 120);
        $this->maxTokens = (int) config('services.ollama.max_tokens', 4096);
    }

    public function completer(
        array $messages,
        ?string $modele = null,
        float $temperature = 0.7,
        ?int $maxTokens = null,
    ): array {
        $debut = microtime(true);

        $response = Http::timeout($this->timeout)
            ->post("{$this->baseUrl}/api/chat", [
                'model' => $modele ?? $this->defaultModel,
                'messages' => $messages,
                'stream' => false,
                'options' => [
                    'temperature' => $temperature,
                    'num_predict' => $maxTokens ?? $this->maxTokens,
                ],
            ]);

        $dureeMs = (int) ((microtime(true) - $debut) * 1000);

        if (! $response->successful()) {
            Log::error('Ollama : erreur de complétion', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException("Erreur Ollama ({$response->status()}) : {$response->body()}");
        }

        $data = $response->json();

        return [
            'content' => $data['message']['content'] ?? '',
            'tokens_entree' => $data['prompt_eval_count'] ?? 0,
            'tokens_sortie' => $data['eval_count'] ?? 0,
            'duree_ms' => $dureeMs,
            'modele' => $data['model'] ?? ($modele ?? $this->defaultModel),
        ];
    }

    public function completerStream(
        array $messages,
        ?string $modele = null,
        float $temperature = 0.7,
        ?int $maxTokens = null,
    ): \Generator {
        $response = Http::timeout($this->timeout)
            ->withOptions(['stream' => true])
            ->post("{$this->baseUrl}/api/chat", [
                'model' => $modele ?? $this->defaultModel,
                'messages' => $messages,
                'stream' => true,
                'options' => [
                    'temperature' => $temperature,
                    'num_predict' => $maxTokens ?? $this->maxTokens,
                ],
            ]);

        $body = $response->toPsrResponse()->getBody();

        while (! $body->eof()) {
            $ligne = '';
            // Lire jusqu'à la fin de ligne
            while (! $body->eof()) {
                $char = $body->read(1);
                if ($char === "\n") {
                    break;
                }
                $ligne .= $char;
            }

            $ligne = trim($ligne);
            if (empty($ligne)) {
                continue;
            }

            $data = json_decode($ligne, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                continue;
            }

            if (isset($data['message']['content'])) {
                yield $data['message']['content'];
            }

            // Dernier message avec les statistiques
            if (! empty($data['done'])) {
                return;
            }
        }
    }

    public function genererEmbedding(string $texte, ?string $modele = null): array
    {
        $response = Http::timeout($this->timeout)
            ->post("{$this->baseUrl}/api/embed", [
                'model' => $modele ?? $this->embeddingModel,
                'input' => $texte,
            ]);

        if (! $response->successful()) {
            Log::error('Ollama : erreur embedding', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException("Erreur Ollama embedding ({$response->status()}) : {$response->body()}");
        }

        $data = $response->json();

        return $data['embeddings'][0] ?? $data['embedding'] ?? [];
    }

    public function estDisponible(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/api/tags");

            return $response->successful();
        } catch (ConnectionException $e) {
            Log::warning('Ollama : serveur indisponible', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function listerModeles(): array
    {
        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/api/tags");

            if (! $response->successful()) {
                return [];
            }

            $data = $response->json();

            return collect($data['models'] ?? [])
                ->map(fn (array $model) => [
                    'nom' => $model['name'],
                    'taille' => $model['size'] ?? null,
                    'modifie_le' => $model['modified_at'] ?? null,
                ])
                ->toArray();
        } catch (ConnectionException $e) {
            return [];
        }
    }
}
