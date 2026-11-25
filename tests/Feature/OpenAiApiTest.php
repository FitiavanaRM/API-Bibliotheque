<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Author;
use App\Models\Book;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

class OpenAiApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.openai.key' => 'cle-de-test']);
    }

    private function auth(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    private function book(array $extra = []): Book
    {
        $author = Author::create(['first_name' => 'Robert', 'last_name' => 'Martin', 'nationality' => 'Américaine']);

        return Book::create(array_merge([
            'title' => 'Clean Code', 'isbn' => '9780132350884', 'year' => 2008,
            'available' => true, 'author_id' => $author->id,
        ], $extra));
    }

    private function fakeAi(string $content): void
    {
        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => $content]]],
            ], 200),
        ]);
    }

    public function test_generation_resume_puis_cache(): void
    {
        $this->auth();
        $book = $this->book();
        $this->fakeAi('Un guide sur le code propre. Il explique les bonnes pratiques. Indispensable.');

        $this->postJson("/api/v1/books/{$book->id}/summary")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.book_id', $book->id)
            ->assertJsonPath('data.title', 'Clean Code')
            ->assertJsonStructure(['data' => ['summary', 'generated_by', 'generated_at']]);

        $this->assertNotEmpty($book->fresh()->summary);
        // le résumé existe déjà, donc pas d'appel à l'IA
        $this->postJson("/api/v1/books/{$book->id}/summary")->assertStatus(200);
        Http::assertSentCount(1);
    }

    public function test_resume_exige_authentification_et_livre_existant(): void
    {
        $book = $this->book();
        $this->postJson("/api/v1/books/{$book->id}/summary")->assertStatus(401);
        $this->auth();
        $this->postJson('/api/v1/books/999/summary')->assertStatus(404);
    }

    public function test_resume_503_si_ia_indisponible(): void
    {
        $this->auth();
        $book = $this->book();
        Http::fake(['*/chat/completions' => Http::response([], 500)]);

        $this->postJson("/api/v1/books/{$book->id}/summary")
            ->assertStatus(503)
            ->assertJsonPath('success', false);

        $this->assertNull($book->fresh()->summary);
    }

    public function test_consultation_resume(): void
    {
        $book = $this->book();
        Http::fake();
        $this->getJson("/api/v1/books/{$book->id}/summary")->assertStatus(404);
        $this->getJson('/api/v1/books/999/summary')->assertStatus(404);

        $book->update([
            'summary' => 'Un résumé.',
            'summary_generated_by' => 'gemini-test',
            'summary_generated_at' => now(),
        ]);

        $this->getJson("/api/v1/books/{$book->id}/summary")
            ->assertStatus(200)
            ->assertJsonPath('data.summary', 'Un résumé.')
            ->assertJsonPath('data.generated_by', 'gemini-test');

        Http::assertNothingSent();
    }

    public function test_recherche_intelligente(): void
    {
        $this->auth();
        $this->book(['title' => 'Clean Code Java', 'isbn' => '111']);
        $this->book(['title' => 'La Peste', 'isbn' => '222']);
        $this->fakeAi("```json\n[\"Java\", \"bonnes pratiques\"]\n```");

        $this->postJson('/api/v1/books/smart-search', [
            'query' => 'Je cherche un livre sur les bonnes pratiques de programmation en Java',
        ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('keywords', ['Java', 'bonnes pratiques'])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Clean Code Java');
    }

    public function test_recherche_validation_auth_et_503(): void
    {
        $this->postJson('/api/v1/books/smart-search', ['query' => 'java'])->assertStatus(401);
        $this->auth();
        $this->postJson('/api/v1/books/smart-search', [])->assertStatus(422);
        Http::fake(['*/chat/completions' => Http::response([], 500)]);
        $this->postJson('/api/v1/books/smart-search', ['query' => 'java'])->assertStatus(503);
    }
}