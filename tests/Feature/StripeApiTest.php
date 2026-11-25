<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Author;
use App\Models\Book;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

class StripeApiTest extends TestCase
{
    use RefreshDatabase;
    private const SECRET = 'whsec_test_secret';
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.stripe.webhook_secret' => self::SECRET]);
    }

    private function user(string $status = 'free'): User
    {
        $user = User::factory()->create();
        $user->forceFill(['subscription_status' => $status])->save();

        return $user;
    }

    private function webhook(array $payload, ?string $secret = self::SECRET, bool $sign = true)
    {
        $json = json_encode($payload);
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        if ($sign) {
            $t = time();
            $sig = hash_hmac('sha256', "{$t}.{$json}", $secret);
            $server['HTTP_STRIPE_SIGNATURE'] = "t={$t},v1={$sig}";
        }

        return $this->call('POST', '/api/v1/webhooks/stripe', [], [], [], $server, $json);
    }

    private function succeeded(string $eventId, array $metadata): array
    {
        return [
            'id' => $eventId,
            'object' => 'event',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_test', 'object' => 'payment_intent', 'metadata' => $metadata]],
        ];
    }

    public function test_payment_intent(): void
    {
        $this->postJson('/api/v1/subscriptions/payment-intent')->assertStatus(401);
        $this->mock(StripeService::class, function ($mock) {
            $mock->shouldReceive('createPremiumPaymentIntent')
                ->once()
                ->andReturn(['id' => 'pi_123', 'client_secret' => 'pi_123_secret_abc']);
            $mock->shouldReceive('publishableKey')->andReturn('pk_test_xxx');
        });

        Sanctum::actingAs($this->user());
        $this->postJson('/api/v1/subscriptions/payment-intent')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.client_secret', 'pi_123_secret_abc')
            ->assertJsonPath('data.amount', 500)
            ->assertJsonPath('data.currency', 'eur')
            ->assertJsonPath('data.publishable_key', 'pk_test_xxx');
    }

    public function test_statut_abonnement(): void
    {
        $this->getJson('/api/v1/subscriptions/status')->assertStatus(401);
        Sanctum::actingAs($this->user('free'));
        $this->getJson('/api/v1/subscriptions/status')
            ->assertStatus(200)
            ->assertJsonPath('data.subscription_status', 'free');

        Sanctum::actingAs($this->user('premium'));
        $this->getJson('/api/v1/subscriptions/status')
            ->assertJsonPath('data.subscription_status', 'premium');
    }

    public function test_webhook_signature_absente_ou_invalide(): void
    {
        $payload = $this->succeeded('evt_1', ['user_id' => '1', 'plan' => 'premium']);
        $this->webhook($payload, sign: false)
            ->assertStatus(401)
            ->assertJsonPath('success', false);
        $this->webhook($payload, 'mauvais_secret')->assertStatus(401);
    }

    public function test_webhook_active_premium_sans_bearer(): void
    {
        $user = $this->user('free');
        $this->webhook($this->succeeded('evt_2', ['user_id' => (string) $user->id, 'plan' => 'premium']))
            ->assertStatus(200)
            ->assertExactJson(['received' => true]);
        $this->assertSame('premium', $user->fresh()->subscription_status);
    }

    public function test_webhook_evenement_repete_traite_une_seule_fois(): void
    {
        $user = $this->user('free');
        $payload = $this->succeeded('evt_3', ['user_id' => (string) $user->id, 'plan' => 'premium']);
        $this->webhook($payload)->assertStatus(200);
        $user->forceFill(['subscription_status' => 'free'])->save(); // on simule un retour en arrière
        $this->webhook($payload)->assertStatus(200);
        $this->assertSame(1, DB::table('stripe_events')->where('event_id', 'evt_3')->count());
        $this->assertSame('free', $user->fresh()->subscription_status); // le doublon n'a rien retraité
    }

    public function test_webhook_ignore_autre_plan_et_autre_evenement(): void
    {
        $user = $this->user('free');
        $this->webhook($this->succeeded('evt_4', ['user_id' => (string) $user->id, 'plan' => 'gold']))
            ->assertStatus(200);
        $this->webhook(['id' => 'evt_5', 'object' => 'event', 'type' => 'charge.refunded', 'data' => ['object' => []]])
            ->assertStatus(200);
        $this->assertSame('free', $user->fresh()->subscription_status);
    }

    private function books(int $n): array
    {
        $author = Author::create(['first_name' => 'A', 'last_name' => 'B', 'nationality' => 'C']);
        $books = [];

        for ($i = 1; $i <= $n; $i++) {
            $books[] = Book::create([
                'title' => "Livre $i", 'isbn' => "ISBN$i", 'year' => 2020,
                'available' => true, 'author_id' => $author->id,
            ]);
        }

        return $books;
    }

    private function borrow(Book $book)
    {
        return $this->postJson('/api/v1/borrows', ['user_name' => 'Jean', 'book_id' => $book->id]);
    }

    public function test_limite_free_a_deux_emprunts(): void
    {
        Sanctum::actingAs($this->user('free'));
        [$b1, $b2, $b3] = $this->books(3);

        $first = $this->borrow($b1);
        $first->assertStatus(201)->assertJsonPath('data.payment_status', 'free');
        $this->borrow($b2)->assertStatus(201);
        $this->borrow($b3)->assertStatus(403)->assertJsonPath('success', false);
        $this->patchJson('/api/v1/borrows/' . $first->json('data.id') . '/return')->assertStatus(200);
        $this->borrow($b3)->assertStatus(201);
    }

    public function test_premium_emprunts_illimites(): void
    {
        Sanctum::actingAs($this->user('premium'));
        foreach ($this->books(3) as $book) {
            $this->borrow($book)->assertStatus(201)->assertJsonPath('data.payment_status', 'paid');
        }
    }
}
