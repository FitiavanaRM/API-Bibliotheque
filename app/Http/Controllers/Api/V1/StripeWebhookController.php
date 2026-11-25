<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Exceptions\StripeUnavailableException;
use App\Models\User;
use App\Services\StripeService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;

class StripeWebhookController extends Controller
{
    //
    use ApiResponse;

    public function __construct(private StripeService $stripe)
    {
        
    }

    public function __invoke(Request $request): JsonResponse
    {
        $signature = $request->header('Stripe-Signature');
        if (! $signature) {
            return $this->error('Signature Stripe manquante', null, 401);
        }

        try {
            $event = $this->stripe->constructEvent($request->getContent(), $signature);
        } catch (SignatureVerificationException|\UnexpectedValueException $e) {
            return $this->error('Signature Stripe invalide', null, 401);
        } catch (StripeUnavailableException $e) {
            return $this->error('Webhook non configuré', null, 503);
        }

        if ($event->type === 'payment_intent.succeeded') {
            $metadata = data_get($event->toArray(), 'data.object.metadata', []);
            $userId = $metadata['user_id'] ?? null;
            $plan = $metadata['plan'] ?? null;

            if ($plan === 'premium' && $userId) {
                $this->activatePremium($event->id, $event->type, (int) $userId);
            } else {
                Log::info('Webhook Stripe ignoré : métadonnées absentes ou plan différent', ['event' => $event->id]);
            }
        }

        return response()->json(['received' => true], 200);
    }

    private function activatePremium(string $eventId, string $type, int $userId): void
    {
        DB::transaction(function () use ($eventId, $type, $userId) {
            $inserted = DB::table('stripe_events')->insertOrIgnore([
                'event_id' => $eventId,
                'type' => $type,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserted === 0) {
                return;
            }

            $user = User::find($userId);

            if (! $user) {
                Log::warning('Webhook Stripe : utilisateur introuvable', ['user_id' => $userId]);
                return;
            }

            $user->subscription_status = 'premium';
            $user->save();
        });
    }
}
