<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Book;
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

    public function test_crud_auteur_et_protection_des_modifications(): void
    {
        $this->postJson('/api/v1/authors', [
            'first_name' => 'Victor',
            'last_name' => 'Hugo',
            'nationality' => 'Française',
        ])->assertUnauthorized();

        $this->auth();

        $authorId = $this->postJson('/api/v1/authors', [
            'first_name' => 'Victor',
            'last_name' => 'Hugo',
            'nationality' => 'Française',
        ])
            ->assertCreated()
            ->assertJsonPath('data.last_name', 'Hugo')
            ->json('data.id');

        $this->getJson("/api/v1/authors/{$authorId}")
            ->assertOk()
            ->assertJsonPath('data.first_name', 'Victor');

        $this->putJson("/api/v1/authors/{$authorId}", ['nationality' => 'Française'])
            ->assertOk()
            ->assertJsonPath('data.nationality', 'Française');

        $this->deleteJson("/api/v1/authors/{$authorId}")->assertNoContent();
        $this->getJson("/api/v1/authors/{$authorId}")->assertNotFound();
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

    public function test_un_utilisateur_ne_peut_pas_voir_ou_rendre_l_emprunt_d_un_autre(): void
    {
        $currentUser = User::factory()->create();
        $otherUser = User::factory()->create();
        $author = Author::create(['first_name' => 'A', 'last_name' => 'B', 'nationality' => 'C']);
        $ownBook = Book::create([
            'title' => 'Livre du compte connecté',
            'isbn' => '111',
            'year' => 2020,
            'available' => false,
            'author_id' => $author->id,
        ]);
        $otherBook = Book::create([
            'title' => 'Livre d’un autre compte',
            'isbn' => '222',
            'year' => 2020,
            'available' => false,
            'author_id' => $author->id,
        ]);
        $ownBorrow = $currentUser->borrows()->create([
            'user_name' => $currentUser->name,
            'book_id' => $ownBook->id,
            'borrowed_at' => now(),
            'payment_status' => 'free',
        ]);
        $otherBorrow = $otherUser->borrows()->create([
            'user_name' => $otherUser->name,
            'book_id' => $otherBook->id,
            'borrowed_at' => now(),
            'payment_status' => 'free',
        ]);

        Sanctum::actingAs($currentUser);

        $this->getJson('/api/v1/borrows')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownBorrow->id);

        $this->patchJson("/api/v1/borrows/{$otherBorrow->id}/return")
            ->assertNotFound();

        $this->assertNull($otherBorrow->fresh()->returned_at);
        $this->assertFalse($otherBook->fresh()->available);
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
