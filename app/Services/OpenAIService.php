<?php

namespace App\Services;

use App\Exceptions\OpenAIUnavailableException;
use App\Models\Book;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIService
{
    public function model(): string
    {
        return (string) config('services.openai.model');
    }

    /**
     * Appel générique à l'endpoint
     */
    public function chat(array $messages, float $temperature = 0.7): string
    {
        $key = config('services.openai.key');
        if (! $key) {
            throw new OpenAIUnavailableException('Clé API IA non configurée.');
        }

        $url = rtrim((string) config('services.openai.base_url'), '/') . '/chat/completions';

        try {
            $response = Http::withToken($key)
                ->acceptJson()
                ->timeout(30)
                ->post($url, [
                    'model' => $this->model(),
                    'messages' => $messages,
                    'temperature' => $temperature,
                ]);
        } catch (ConnectionException $e) {
            Log::warning('OpenAI : connexion impossible', ['error' => $e->getMessage()]);
            throw new OpenAIUnavailableException('Service IA injoignable.', 0, $e);
        }

        if ($response->failed()) {
            Log::warning('OpenAI : réponse en erreur', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);
            throw new OpenAIUnavailableException('Le service IA a répondu avec une erreur.');
        }

        $content = data_get($response->json(), 'choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new OpenAIUnavailableException('Réponse vide du service IA.');
        }

        return trim($content);
    }

    public function generateSummary(Book $book): string
    {
        $author = $book->author;
        return $this->chat([
            [
                'role' => 'system',
                'content' => 'Tu es un expert en littérature. Tu génères des résumés concis et accrocheurs pour des fiches de bibliothèque. Réponds toujours en français. Maximum 3 phrases.',
            ],
            [
                'role' => 'user',
                'content' => "Génère un résumé pour le livre intitulé \"{$book->title}\" écrit par {$author->first_name} {$author->last_name}, publié en {$book->year}.",
            ],
        ]);
    }

    /**
     * @return string[] mots-clés extraits de la description
     */
    public function extractKeywords(string $query): array
    {
        $raw = $this->chat([
            [
                'role' => 'system',
                'content' => 'Tu extrais des mots-clés de recherche pour une bibliothèque. Réponds UNIQUEMENT avec un tableau JSON de chaînes de caractères, par exemple ["mot1","mot2"]. Aucun texte autour, aucune balise markdown. Maximum 5 mots-clés.',
            ],
            [
                'role' => 'user',
                'content' => $query,
            ],
        ], 0.2);

        return $this->parseKeywords($raw);
    }

    private function parseKeywords(string $raw): array
    {
        $clean = trim(preg_replace('/^```(?:json)?|```$/m', '', $raw));

        $decoded = json_decode($clean, true);

        if (! is_array($decoded) && preg_match('/\[.*\]/s', $clean, $m)) {
            $decoded = json_decode($m[0], true);
        }

        if (is_array($decoded) && isset($decoded['keywords']) && is_array($decoded['keywords'])) {
            $decoded = $decoded['keywords'];
        }

        if (! is_array($decoded)) {
            throw new OpenAIUnavailableException('Réponse IA inexploitable.');
        }

        return collect($decoded)
            ->filter(fn ($k) => is_string($k))
            ->map(fn ($k) => trim($k))
            ->filter()
            ->unique()
            ->take(8)
            ->values()
            ->all();
    }
}