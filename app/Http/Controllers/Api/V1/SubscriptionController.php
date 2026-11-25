<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\StripeUnavailableException;
use App\Http\Controllers\Controller;
use App\Services\StripeService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    use ApiResponse;

    public function __construct(private StripeService $stripe)
    {
    }

    /**
     * POST /subscriptions/payment-intent
     */
    public function paymentIntent(Request $request): JsonResponse
    {
        try {
            $intent = $this->stripe->createPremiumPaymentIntent($request->user());
        } catch (StripeUnavailableException $e) {
            return $this->error('Service de paiement indisponible', null, 503);
        }

        return $this->success([
            'client_secret' => $intent['client_secret'],
            'amount' => StripeService::PREMIUM_AMOUNT,
            'currency' => StripeService::CURRENCY,
            'publishable_key' => $this->stripe->publishableKey(),
        ], 'PaymentIntent créé avec succès');
    }

    /**
     * GET /subscriptions/status
     */
    public function status(Request $request): JsonResponse
    {
        return $this->success([
            'subscription_status' => $request->user()->subscription_status ?? 'free',
        ], 'Statut de l\'abonnement');
    }
}