<?php

$file = __DIR__ . '/../public/openapi.yaml';
$yaml = str_replace("\r\n", "\n", file_get_contents($file));

if (str_contains($yaml, '/webhooks/stripe:')) {
    echo "Déjà à jour : aucune modification." . PHP_EOL;
    exit(0);
}

// ---------- 1. Nouveaux endpoints (insérés juste avant "components:") ----------
$paths = <<<'YAML'

  /books/smart-search:
    post:
      tags: [Books]
      summary: Recherche intelligente de livres
      description: L'IA extrait des mots-clés de la description. Les livres retournés proviennent uniquement de la base de données (l'IA n'invente aucun livre).
      operationId: smartSearchBooks
      security:
        - BearerAuth: []
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/SmartSearchInput'
            example:
              query: Je cherche un livre sur les bonnes pratiques de programmation en Java
      responses:
        '200':
          description: Mots-clés extraits et livres correspondants
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/SmartSearchResponse'
        '401':
          $ref: '#/components/responses/Unauthorized'
        '422':
          $ref: '#/components/responses/ValidationError'
        '503':
          $ref: '#/components/responses/ServiceUnavailable'

  /books/{id}/summary:
    get:
      tags: [Books]
      summary: Consulter le résumé d'un livre
      description: Retourne le résumé déjà enregistré, sans appeler l'IA.
      operationId: getBookSummary
      security: []
      parameters:
        - $ref: '#/components/parameters/IdParam'
      responses:
        '200':
          description: Résumé enregistré
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/SummaryResponse'
        '404':
          description: Livre introuvable, ou aucun résumé généré pour ce livre
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ErrorResponse'
              example:
                success: false
                message: Aucun résumé disponible pour ce livre
                errors:
                  summary:
                    - Générez-le d'abord avec POST /books/1/summary.
    post:
      tags: [Books]
      summary: Générer le résumé d'un livre avec l'IA
      description: Si le résumé existe déjà, il est retourné sans appeler l'IA. Sinon il est généré (3 phrases maximum, en français) puis enregistré.
      operationId: generateBookSummary
      security:
        - BearerAuth: []
      parameters:
        - $ref: '#/components/parameters/IdParam'
      responses:
        '200':
          description: Résumé généré ou déjà existant
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/SummaryResponse'
        '401':
          $ref: '#/components/responses/Unauthorized'
        '404':
          $ref: '#/components/responses/NotFound'
        '503':
          $ref: '#/components/responses/ServiceUnavailable'

  /subscriptions/payment-intent:
    post:
      tags: [Subscriptions]
      summary: Créer un PaymentIntent pour l'abonnement Premium
      description: Crée un paiement Stripe de 500 centimes (5 EUR) avec les métadonnées `user_id` et `plan=premium`. Clés Stripe en mode test uniquement.
      operationId: createPaymentIntent
      security:
        - BearerAuth: []
      responses:
        '200':
          description: PaymentIntent créé
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/PaymentIntentResponse'
        '401':
          $ref: '#/components/responses/Unauthorized'
        '503':
          $ref: '#/components/responses/ServiceUnavailable'

  /subscriptions/status:
    get:
      tags: [Subscriptions]
      summary: Consulter le statut d'abonnement de l'utilisateur connecté
      operationId: getSubscriptionStatus
      security:
        - BearerAuth: []
      responses:
        '200':
          description: Statut de l'abonnement
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/SubscriptionStatusResponse'
        '401':
          $ref: '#/components/responses/Unauthorized'

  /webhooks/stripe:
    post:
      tags: [Webhooks]
      summary: Recevoir les événements de paiement Stripe
      description: |
        Route publique **sans BearerAuth** : l'authenticité est garantie par la signature Stripe (`Stripe-Signature`, HMAC-SHA256 calculé avec `STRIPE_WEBHOOK_SECRET`).
        Sur `payment_intent.succeeded` avec `plan=premium`, l'utilisateur passe en Premium. Un événement déjà traité est ignoré.

        Swagger UI ne peut pas calculer la signature : pour un test valide, utilisez la requête Postman « Simulate Stripe Webhook ».
      operationId: stripeWebhook
      security: []
      parameters:
        - name: Stripe-Signature
          in: header
          required: true
          description: Signature Stripe au format `t=timestamp,v1=signature`.
          schema:
            type: string
          example: t=1700000000,v1=5257a869e7ecebeda32affa62cdca3fa51cad7e77a0e56ff536d0ce8e108d8bd
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/StripeEvent'
            example:
              id: evt_1234567890
              object: event
              type: payment_intent.succeeded
              data:
                object:
                  id: pi_1234567890
                  object: payment_intent
                  metadata:
                    user_id: '1'
                    plan: premium
      responses:
        '200':
          description: Événement reçu et vérifié
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/WebhookResponse'
              example:
                received: true
        '401':
          description: Signature Stripe absente ou invalide
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ErrorResponse'
              example:
                success: false
                message: Signature Stripe invalide
                errors: {}
        '503':
          $ref: '#/components/responses/ServiceUnavailable'

YAML;

// ---------- 2. Nouvelles réponses réutilisables ----------
$responses = <<<'YAML'
    Forbidden:
      description: Limite d'emprunts atteinte (abonnement FREE, 2 emprunts actifs maximum)
      content:
        application/json:
          schema:
            $ref: '#/components/schemas/ErrorResponse'
          example:
            success: false
            message: "Limite d'emprunts atteinte : un abonnement FREE permet au maximum 2 emprunts simultanés. Passez à Premium pour emprunter sans limite."
            errors: {}
    ServiceUnavailable:
      description: Service externe indisponible (IA ou paiement)
      content:
        application/json:
          schema:
            $ref: '#/components/schemas/ErrorResponse'
          example:
            success: false
            message: Service OpenAI indisponible
            errors: {}

YAML;

// ---------- 3. Nouveaux schémas (ajoutés à la fin du fichier) ----------
$schemas = <<<'YAML'

    SmartSearchInput:
      type: object
      required: [query]
      properties:
        query:
          type: string
          minLength: 3
          maxLength: 500
          example: Je cherche un livre sur les bonnes pratiques de programmation en Java

    SmartSearchResponse:
      type: object
      properties:
        success:
          type: boolean
          example: true
        query:
          type: string
          example: Je cherche un livre sur les bonnes pratiques de programmation en Java
        keywords:
          type: array
          items:
            type: string
          example: [programmation, Java, bonnes pratiques]
        data:
          type: array
          items:
            $ref: '#/components/schemas/Book'

    SummaryResponse:
      type: object
      properties:
        success:
          type: boolean
          example: true
        data:
          type: object
          properties:
            book_id:
              type: integer
              example: 1
            title:
              type: string
              example: Les Misérables
            summary:
              type: string
              example: Dans la France du XIXe siècle, Jean Valjean tente de se racheter. Traqué par l'inspecteur Javert, il croise le destin de Cosette. Une fresque sur la misère et la justice.
            generated_by:
              type: string
              example: gemini-2.5-flash
            generated_at:
              type: string
              format: date-time
              example: '2026-04-23T08:15:00Z'
        message:
          type: string
          example: Résumé généré avec succès

    PaymentIntentResponse:
      type: object
      properties:
        success:
          type: boolean
          example: true
        data:
          type: object
          properties:
            client_secret:
              type: string
              example: pi_3Abc123_secret_xyz789
            amount:
              type: integer
              example: 500
            currency:
              type: string
              example: eur
            publishable_key:
              type: string
              example: pk_test_xxx
        message:
          type: string
          example: PaymentIntent créé avec succès

    SubscriptionStatusResponse:
      type: object
      properties:
        success:
          type: boolean
          example: true
        data:
          type: object
          properties:
            subscription_status:
              type: string
              enum: [free, premium]
              example: free
        message:
          type: string
          example: Statut de l'abonnement

    StripeEvent:
      type: object
      required: [id, type, data]
      properties:
        id:
          type: string
          example: evt_1234567890
        object:
          type: string
          example: event
        type:
          type: string
          example: payment_intent.succeeded
        data:
          type: object
          properties:
            object:
              type: object
              properties:
                id:
                  type: string
                  example: pi_1234567890
                metadata:
                  type: object
                  properties:
                    user_id:
                      type: string
                      example: '1'
                    plan:
                      type: string
                      example: premium

    WebhookResponse:
      type: object
      properties:
        received:
          type: boolean
          example: true
YAML;

// ---------- Application des modifications ----------
function replaceOnce(string $yaml, string $search, string $replace, string $label): string
{
    if (substr_count($yaml, $search) !== 1) {
        fwrite(STDERR, "ERREUR : repère introuvable ou ambigu pour « $label ». Aucune modification enregistrée." . PHP_EOL);
        exit(1);
    }
    echo "OK : $label" . PHP_EOL;
    return str_replace($search, $replace, $yaml);
}

// Tags
$yaml = replaceOnce($yaml,
    "  - name: Borrows\n    description: Gestion des emprunts\n",
    "  - name: Borrows\n    description: Gestion des emprunts\n"
    . "  - name: Subscriptions\n    description: Abonnement Premium (Stripe)\n"
    . "  - name: Webhooks\n    description: Événements envoyés par Stripe\n",
    'tags Subscriptions et Webhooks');

// Endpoints
$yaml = replaceOnce($yaml, "\ncomponents:\n", $paths . "components:\n", 'nouveaux endpoints');

// 403 sur POST /borrows
$yaml = replaceOnce($yaml,
    "        '401':\n          \$ref: '#/components/responses/Unauthorized'\n        '422':\n          description: Données invalides ou livre indisponible\n",
    "        '401':\n          \$ref: '#/components/responses/Unauthorized'\n        '403':\n          \$ref: '#/components/responses/Forbidden'\n        '422':\n          description: Données invalides ou livre indisponible\n",
    'réponse 403 sur POST /borrows');

// Réponses réutilisables
$yaml = replaceOnce($yaml, "\n  schemas:\n", "\n" . $responses . "  schemas:\n", 'réponses Forbidden et ServiceUnavailable');

// Champs ajoutés à Book
$yaml = replaceOnce($yaml,
    "        author:\n          \$ref: '#/components/schemas/Author'\n\n    AuthorWithBooks:",
    "        summary:\n          type: string\n          nullable: true\n          description: Résumé généré par l'IA.\n"
    . "        summary_generated_by:\n          type: string\n          nullable: true\n          example: gemini-2.5-flash\n"
    . "        summary_generated_at:\n          type: string\n          format: date-time\n          nullable: true\n"
    . "        author:\n          \$ref: '#/components/schemas/Author'\n\n    AuthorWithBooks:",
    'nouveaux champs de Book');

// Champs ajoutés à Borrow
$yaml = replaceOnce($yaml,
    "        book:\n          \$ref: '#/components/schemas/Book'\n",
    "        user_id:\n          type: integer\n          nullable: true\n          example: 1\n"
    . "        payment_status:\n          type: string\n          enum: [pending, paid, free]\n          example: free\n"
    . "        book:\n          \$ref: '#/components/schemas/Book'\n",
    'nouveaux champs de Borrow');

// Nouveaux schémas
$yaml = rtrim($yaml) . "\n" . $schemas . "\n";
echo "OK : nouveaux schémas" . PHP_EOL;

file_put_contents($file, $yaml);
echo PHP_EOL . "public\\openapi.yaml mis à jour." . PHP_EOL;