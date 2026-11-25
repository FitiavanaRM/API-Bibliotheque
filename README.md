# Bibliothèque API

API REST réalisée avec Laravel pour gérer un catalogue de livres, les auteurs et les emprunts. L’API inclut une authentification par jeton Sanctum, des fonctions d’IA pour la recherche et les résumés, ainsi qu’un paiement Premium traité par Stripe.

## Prérequis

- PHP 8.3 ou supérieur
- Composer
- SQLite (configuration utilisée par défaut)

## Installation locale

Depuis la racine du projet :

```powershell
composer install
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

L’API est alors disponible à `http://127.0.0.1:8000/api/v1` et Swagger UI à `http://127.0.0.1:8000/api/docs`.

Le seeder crée un compte de démonstration : `admin@bibliotheque.test` / `password123`. Ce mot de passe est réservé au développement local; changez-le avant tout déploiement.

## Authentification

Les consultations des livres et des auteurs sont publiques. Les créations, modifications et suppressions, les emprunts, la recherche IA, la génération de résumés et les routes d’abonnement nécessitent un jeton Bearer.

1. Envoyer `POST /auth/login` avec `email` et `password`.
2. Copier `data.token` dans l’en-tête `Authorization: Bearer <jeton>`.

Le webhook Stripe est public au niveau HTTP, mais refuse les requêtes dont la signature Stripe est absente ou invalide.

## Fonctionnalités principales

- CRUD des livres et des auteurs; les livres sont associés à un auteur.
- Emprunt et retour des livres disponibles. Un compte gratuit peut avoir deux emprunts actifs; les comptes Premium ne sont pas soumis à cette limite.
- Recherche intelligente et génération de résumés via une API compatible avec le format OpenAI Chat Completions. La configuration d’exemple utilise Gemini (`OPENAI_BASE_URL` et `OPENAI_MODEL` dans `.env`). Configurez une clé et une URL correspondant au fournisseur choisi.
- Paiement Stripe de test par PaymentIntent de 5 EUR; un webhook signé peut activer le statut Premium. L’implémentation actuelle effectue un paiement ponctuel et ne gère pas le renouvellement d’un abonnement.

## Configuration des services externes

Les paramètres sont définis dans `.env` et lus depuis `config/services.php` :

```dotenv
OPENAI_API_KEY=
OPENAI_BASE_URL=https://generativelanguage.googleapis.com/v1beta/openai/
OPENAI_MODEL=gemini-2.5-flash

STRIPE_PUBLISHABLE_KEY=
STRIPE_SECRET_KEY=
STRIPE_WEBHOOK_SECRET=
```

Renseignez des clés de test Stripe pour le développement. Ne publiez jamais `.env` ni de clé secrète dans le dépôt. Les tests automatisés simulent les réponses IA et Stripe; ils ne nécessitent pas de clés réelles.

## Swagger et Postman

- Swagger UI : `/api/docs`.
- La spécification servie par l’application est `public/openapi.yaml`; gardez-la synchronisée avec `openapi.yaml` à la racine.
- Collection et environnements : dossier `postman/`.
- Pour régénérer les fichiers Postman après une modification du générateur :

```powershell
php tools/generate_postman.php
```

La simulation Postman d’un webhook signé demande un `stripe_webhook_secret` de test dans l’environnement sélectionné. Connectez-vous d’abord pour remplir automatiquement le jeton et l’identifiant utilisateur.

## Tests

Exécuter tous les tests :

```powershell
php artisan test
```

Les appels aux services externes sont simulés dans les tests. Pour tester un paiement réel en mode test ou un webhook Stripe émis par Stripe, configurez les clés de test et Stripe CLI séparément.

> `php artisan migrate:fresh --seed` supprime toutes les tables avant de les recréer. Utilisez cette commande uniquement sur une base de développement ou de test.
