<?php

namespace App\Services;

use App\Exceptions\StripeUnavailableException;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeService
{
    public const PREMIUM_AMOUNT = 500;
    public const CURRENCY = 'eur';

    public function publishableKey(): ?string
    {
        return config('services.stripe.publishable_key');
    }

    /**
     * Crée un PaymentIntent de 5 EUR pour l'abonnement Premium
     * @return array{id: string, client_secret: string}
     */
    public function createPremiumPaymentIntent(User $user): array
    {
        $secret = config('services.stripe.secret_key');

        if (! $secret) {
            throw new StripeUnavailableException('Clé secrète Stripe non configurée.');
        }

        if (! str_starts_with($secret, 'sk_test_') && ! str_starts_with($secret, 'rk_test_')) {
            throw new StripeUnavailableException('Seules les clés Stripe de test sont autorisées.');
        }

        try {
            $intent = (new StripeClient($secret))->paymentIntents->create([
                'amount' => self::PREMIUM_AMOUNT,
                'currency' => self::CURRENCY,
                'payment_method_types' => ['card'],
                'metadata' => [
                    'user_id' => (string) $user->id,
                    'plan' => 'premium',
                ],
            ]);
        } catch (ApiErrorException $e) {
            Log::warning('Stripe : erreur API', ['error' => $e->getMessage()]);
            throw new StripeUnavailableException('Service de paiement indisponible.', 0, $e);
        }

        return ['id' => $intent->id, 'client_secret' => $intent->client_secret];
    }

    /**
     * Vérifie la signature du webhook et retourne l'événement.
     *
     * @throws \Stripe\Exception\SignatureVerificationException
     * @throws \UnexpectedValueException
     */
    public function constructEvent(string $payload, string $signature): Event
    {
        $secret = config('services.stripe.webhook_secret');

        if (! $secret) {
            throw new StripeUnavailableException('Secret du webhook non configuré.');
        }

        return Webhook::constructEvent($payload, $signature, $secret);
    }
}