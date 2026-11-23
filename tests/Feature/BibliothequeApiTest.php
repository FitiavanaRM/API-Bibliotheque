<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Book;
use App\Models\Borrow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BibliothequeApiTest extends TestCase
{
    use RefreshDatabase;

    private function auth(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_login_ok_et_ko(): void
    {
        User::factory()->create(['email' => 'a@test.com', 'password' => 'secret123']);

        $this->postJson('/api/v1/auth/login', ['email' => 'a@test.com', 'password' => 'secret123'])
            ->assertStatus(200)->assertJsonPath('success', true)->assertJsonStructure(['data' => ['token']]);

        $this->postJson('/api/v1/auth/login', ['email' => 'a@test.com', 'password' => 'faux'])
            ->assertStatus(401)->assertJsonPath('success', false);

        $this->postJson('/api/v1/auth/login', [])->assertStatus(422);
    }

    public function test_route_protegee_sans_token(): void
    {
        $this->getJson('/api/v1/borrows')->assertStatus(401);
    }

    public function test_creation_livre(): void
    {
        $this->auth();
        $author = Author::create(['first_name' => 'A', 'last_name' => 'B', 'nationality' => 'C']);

        $this->postJson('/api/v1/books', ['title' => 'T', 'isbn' => '111', 'year' => 2020, 'author_id' => $author->id])
            ->assertStatus(201)->assertJsonPath('data.available', true);

        $this->postJson('/api/v1/books', ['title' => 'T2', 'isbn' => '111', 'year' => 2020, 'author_id' => $author->id])
            ->assertStatus(422);

        $this->postJson('/api/v1/books', ['title' => 'T3', 'isbn' => '222', 'year' => 2020, 'author_id' => 999])
            ->assertStatus(422);
    }

    public function test_emprunt_et_retour(): void
    {
        $this->auth();
        $author = Author::create(['first_name' => 'A', 'last_name' => 'B', 'nationality' => 'C']);
        $book = Book::create(['title' => 'T', 'isbn' => '111', 'year' => 2020, 'available' => true, 'author_id' => $author->id]);

        $r = $this->postJson('/api/v1/borrows', ['user_name' => 'Jean', 'book_id' => $book->id]);
        $r->assertStatus(201);
        $this->assertFalse($book->fresh()->available);
        $borrowId = $r->json('data.id');

        $this->postJson('/api/v1/borrows', ['user_name' => 'Autre', 'book_id' => $book->id])->assertStatus(422);

        $this->patchJson("/api/v1/borrows/{$borrowId}/return")->assertStatus(200);
        $this->assertTrue($book->fresh()->available);

        $this->patchJson("/api/v1/borrows/{$borrowId}/return")->assertStatus(422);
        $this->patchJson('/api/v1/borrows/999/return')->assertStatus(404);
    }

    public function test_suppression_livre(): void
    {
        $this->auth();
        $author = Author::create(['first_name' => 'A', 'last_name' => 'B', 'nationality' => 'C']);
        $book = Book::create(['title' => 'T', 'isbn' => '111', 'year' => 2020, 'available' => true, 'author_id' => $author->id]);

        $this->deleteJson("/api/v1/books/{$book->id}")->assertStatus(204);
        $this->deleteJson('/api/v1/books/999')->assertStatus(404);
    }
}