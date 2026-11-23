<?php

// ---------- Fonctions utilitaires pour écrire les tests ----------
function t(string $name, string $body): string
{
    return "pm.test('{$name}', function () { const j = pm.response.json(); {$body} });";
}

function status(int $c): string
{
    return "pm.test('Statut HTTP {$c}', function () { pm.response.to.have.status({$c}); });";
}

function successIs(bool $v): string
{
    $s = $v ? 'true' : 'false';
    return t("success vaut {$s}", "pm.expect(j.success).to.eql({$s});");
}

function keysOk(): string
{
    return t('Structure JSON : success, data, message', "pm.expect(j).to.have.all.keys('success', 'data', 'message');");
}

function keysErr(): string
{
    return t('Structure JSON : success, message, errors', "pm.expect(j).to.have.all.keys('success', 'message', 'errors');");
}

function errorOn(string $field): string
{
    return t("Erreur attendue sur {$field}", "pm.expect(j.errors).to.have.property('{$field}');");
}

function noBody(): string
{
    return "pm.test('Corps de réponse vide', function () { pm.expect(pm.response.text()).to.eql(''); });";
}

function saveId(string $var, int $code): string
{
    return "if (pm.response.code === {$code}) { pm.environment.set('{$var}', pm.response.json().data.id); }";
}

function sameId(string $var): string
{
    return t("L'identifiant correspond à {$var}", "pm.expect(j.data.id).to.eql(parseInt(pm.environment.get('{$var}')));");
}

// ---------- Construction d'une requête ----------
function req(string $name, string $method, array $path, array $o = []): array
{
    $protected = $o['protected'] ?? true;
    $url = [
        'raw' => '{{base_url}}/' . implode('/', $path),
        'host' => ['{{base_url}}'],
        'path' => $path,
    ];

    if (!empty($o['query'])) {
        $q = [];
        $parts = [];
        foreach ($o['query'] as $k => $v) {
            $q[] = ['key' => $k, 'value' => $v];
            $parts[] = "{$k}={$v}";
        }
        $url['query'] = $q;
        $url['raw'] .= '?' . implode('&', $parts);
    }

    $request = [
        'method' => $method,
        'header' => [
            ['key' => 'Accept', 'value' => 'application/json'],
            ['key' => 'Content-Type', 'value' => 'application/json'],
        ],
        'url' => $url,
    ];

    if (!$protected) {
        $request['auth'] = ['type' => 'noauth'];
    }

    if (isset($o['body'])) {
        $request['body'] = [
            'mode' => 'raw',
            'raw' => $o['body'],
            'options' => ['raw' => ['language' => 'json']],
        ];
    }

    $events = [];
    if (!empty($o['pre'])) {
        $events[] = ['listen' => 'prerequest', 'script' => ['type' => 'text/javascript', 'exec' => $o['pre']]];
    }
    $events[] = ['listen' => 'test', 'script' => ['type' => 'text/javascript', 'exec' => $o['tests']]];

    return ['name' => $name, 'event' => $events, 'request' => $request];
}

function uuid(string $seed): string
{
    $h = md5($seed);
    return substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-' . substr($h, 12, 4) . '-' . substr($h, 16, 4) . '-' . substr($h, 20, 12);
}

// ---------- Dossier 1 : Authentification ----------
$auth = [
    req('POST /auth/login - Connexion', 'POST', ['auth', 'login'], [
        'protected' => false,
        'body' => "{\n  \"email\": \"admin@bibliotheque.test\",\n  \"password\": \"password123\"\n}",
        'tests' => [
            status(200),
            successIs(true),
            keysOk(),
            t('Un token est retourné', "pm.expect(j.data.token).to.be.a('string').and.not.empty;"),
            t('Le type de token est Bearer', "pm.expect(j.data.token_type).to.eql('Bearer');"),
            "if (pm.response.code === 200) { pm.environment.set('token', pm.response.json().data.token); }",
        ],
    ]),
    req('POST /auth/login - Identifiants invalides (401)', 'POST', ['auth', 'login'], [
        'protected' => false,
        'body' => "{\n  \"email\": \"admin@bibliotheque.test\",\n  \"password\": \"mauvais\"\n}",
        'tests' => [status(401), successIs(false), keysErr()],
    ]),
];

// ---------- Dossier 2 : Authors ----------
$authors = [
    req('GET /authors - Liste des auteurs', 'GET', ['authors'], [
        'protected' => false,
        'tests' => [
            status(200),
            successIs(true),
            keysOk(),
            t('data est un tableau non vide', "pm.expect(j.data).to.be.an('array').that.is.not.empty;"),
        ],
    ]),
    req('POST /authors - Créer un auteur', 'POST', ['authors'], [
        'body' => "{\n  \"first_name\": \"Test\",\n  \"last_name\": \"Postman\",\n  \"nationality\": \"Malgache\"\n}",
        'tests' => [
            status(201),
            successIs(true),
            keysOk(),
            t("L'auteur créé a un identifiant", "pm.expect(j.data.id).to.be.a('number'); pm.expect(j.data.last_name).to.eql('Postman');"),
            saveId('author_id', 201),
        ],
    ]),
    req('POST /authors - Données invalides (422)', 'POST', ['authors'], [
        'body' => "{\n  \"first_name\": \"\"\n}",
        'tests' => [status(422), successIs(false), keysErr(), errorOn('first_name')],
    ]),
    req('GET /authors/{id} - Afficher un auteur et ses livres', 'GET', ['authors', '{{author_id}}'], [
        'protected' => false,
        'tests' => [
            status(200),
            successIs(true),
            sameId('author_id'),
            t('Les livres sont inclus (tableau)', "pm.expect(j.data.books).to.be.an('array');"),
        ],
    ]),
    req('GET /authors/{id} - Auteur introuvable (404)', 'GET', ['authors', '999999'], [
        'protected' => false,
        'tests' => [status(404), successIs(false), keysErr()],
    ]),
    req('PUT /authors/{id} - Modifier un auteur', 'PUT', ['authors', '{{author_id}}'], [
        'body' => "{\n  \"nationality\": \"Malgache (modifié)\"\n}",
        'tests' => [
            status(200),
            successIs(true),
            sameId('author_id'),
            t('La nationalité est modifiée', "pm.expect(j.data.nationality).to.eql('Malgache (modifié)');"),
        ],
    ]),
    req('DELETE /authors/{id} - Supprimer un auteur', 'DELETE', ['authors', '{{author_id}}'], [
        'tests' => [status(204), noBody()],
    ]),
];

// ---------- Dossier 3 : Books ----------
$books = [
    req('GET /books - Liste des livres', 'GET', ['books'], [
        'protected' => false,
        'tests' => [
            status(200),
            successIs(true),
            keysOk(),
            t('Chaque livre contient son auteur', "pm.expect(j.data).to.be.an('array').that.is.not.empty; pm.expect(j.data[0]).to.have.property('author');"),
        ],
    ]),
    req('GET /books?available=false - Livres indisponibles', 'GET', ['books'], [
        'protected' => false,
        'query' => ['available' => 'false'],
        'tests' => [
            status(200),
            successIs(true),
            t('Tous les livres sont indisponibles', "pm.expect(j.data.every(function (b) { return b.available === false; })).to.eql(true);"),
        ],
    ]),
    req('POST /books - Ajouter un livre', 'POST', ['books'], [
        'pre' => ["pm.environment.set('isbn', '978' + Date.now());"],
        'body' => "{\n  \"title\": \"Livre Postman\",\n  \"isbn\": \"{{isbn}}\",\n  \"year\": 2020,\n  \"author_id\": 1\n}",
        'tests' => [
            status(201),
            successIs(true),
            keysOk(),
            t('Le livre est disponible et lié à auteur 1', "pm.expect(j.data.available).to.eql(true); pm.expect(j.data.author_id).to.eql(1);"),
            saveId('book_id', 201),
        ],
    ]),
    req('POST /books - ISBN en doublon (422)', 'POST', ['books'], [
        'body' => "{\n  \"title\": \"Doublon\",\n  \"isbn\": \"{{isbn}}\",\n  \"year\": 2020,\n  \"author_id\": 1\n}",
        'tests' => [status(422), successIs(false), keysErr(), errorOn('isbn')],
    ]),
    req('POST /books - Auteur inexistant (422)', 'POST', ['books'], [
        'body' => "{\n  \"title\": \"Sans auteur\",\n  \"isbn\": \"9789999999999\",\n  \"year\": 2020,\n  \"author_id\": 999999\n}",
        'tests' => [status(422), successIs(false), errorOn('author_id')],
    ]),
    req('GET /books/{id} - Afficher un livre et son auteur', 'GET', ['books', '{{book_id}}'], [
        'protected' => false,
        'tests' => [
            status(200),
            successIs(true),
            sameId('book_id'),
            t("L'auteur est inclus", "pm.expect(j.data.author).to.be.an('object'); pm.expect(j.data.author.id).to.eql(j.data.author_id);"),
        ],
    ]),
    req('GET /books/{id} - Livre introuvable (404)', 'GET', ['books', '999999'], [
        'protected' => false,
        'tests' => [status(404), successIs(false), keysErr()],
    ]),
    req('PUT /books/{id} - Modifier un livre', 'PUT', ['books', '{{book_id}}'], [
        'body' => "{\n  \"title\": \"Livre Postman (modifié)\"\n}",
        'tests' => [
            status(200),
            successIs(true),
            sameId('book_id'),
            t('Le titre est modifié', "pm.expect(j.data.title).to.eql('Livre Postman (modifié)');"),
        ],
    ]),
    req('DELETE /books/{id} - Supprimer un livre', 'DELETE', ['books', '{{book_id}}'], [
        'tests' => [status(204), noBody()],
    ]),
];

// ---------- Dossier 4 : Borrows ----------
// Le livre 4 (La Peste) est disponible dans les seeders.
$borrows = [
    req('GET /borrows - Sans token (401)', 'GET', ['borrows'], [
        'protected' => false,
        'tests' => [status(401), successIs(false), keysErr()],
    ]),
    req('GET /borrows - Emprunts en cours', 'GET', ['borrows'], [
        'tests' => [
            status(200),
            successIs(true),
            keysOk(),
            t('Aucun emprunt retourné dans la liste', "pm.expect(j.data).to.be.an('array'); pm.expect(j.data.every(function (b) { return b.returned_at === null; })).to.eql(true);"),
        ],
    ]),
    req('POST /borrows - Enregistrer un emprunt', 'POST', ['borrows'], [
        'body' => "{\n  \"user_name\": \"Postman User\",\n  \"book_id\": 4\n}",
        'tests' => [
            status(201),
            successIs(true),
            keysOk(),
            t("L'emprunt est en cours et le livre est indisponible", "pm.expect(j.data.returned_at).to.eql(null); pm.expect(j.data.book.available).to.eql(false);"),
            saveId('borrow_id', 201),
        ],
    ]),
    req('POST /borrows - Livre indisponible (422)', 'POST', ['borrows'], [
        'body' => "{\n  \"user_name\": \"Autre\",\n  \"book_id\": 4\n}",
        'tests' => [status(422), successIs(false), keysErr(), errorOn('book_id')],
    ]),
    req('PATCH /borrows/{id}/return - Rendre un livre', 'PATCH', ['borrows', '{{borrow_id}}', 'return'], [
        'tests' => [
            status(200),
            successIs(true),
            sameId('borrow_id'),
            t('La date de retour est renseignée', "pm.expect(j.data.returned_at).to.be.a('string');"),
        ],
    ]),
    req('PATCH /borrows/{id}/return - Retour multiple (422)', 'PATCH', ['borrows', '{{borrow_id}}', 'return'], [
        'tests' => [status(422), successIs(false), keysErr(), errorOn('borrow')],
    ]),
    req('PATCH /borrows/{id}/return - Emprunt introuvable (404)', 'PATCH', ['borrows', '999999', 'return'], [
        'tests' => [status(404), successIs(false), keysErr()],
    ]),
];

// ---------- Collection ----------
$collection = [
    'info' => [
        '_postman_id' => uuid('collection'),
        'name' => 'API Bibliothèque — HERIFITIAVANA Rotsy Nomena',
        'description' => 'API REST de gestion de bibliothèque (Laravel, Sanctum). Sélectionnez un environnement, exécutez le login, puis le reste.',
        'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
    ],
    'auth' => [
        'type' => 'bearer',
        'bearer' => [['key' => 'token', 'value' => '{{token}}', 'type' => 'string']],
    ],
    'item' => [
        ['name' => '1. Authentification', 'item' => $auth],
        ['name' => '2. Authors', 'item' => $authors],
        ['name' => '3. Books', 'item' => $books],
        ['name' => '4. Borrows', 'item' => $borrows],
    ],
];

// ---------- Environnements ----------
function environment(string $name, string $baseUrl): array
{
    $vars = ['base_url' => $baseUrl, 'token' => '', 'book_id' => '1', 'author_id' => '1', 'borrow_id' => '1'];
    $values = [];
    foreach ($vars as $k => $v) {
        $values[] = ['key' => $k, 'value' => $v, 'type' => 'default', 'enabled' => true];
    }
    return [
        'id' => uuid('env-' . $name),
        'name' => $name,
        'values' => $values,
        '_postman_variable_scope' => 'environment',
    ];
}

$flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
$dir = __DIR__ . '/../postman';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

file_put_contents($dir . '/API_Bibliotheque_HERIFITIAVANA_Rotsy_Nomena.postman_collection.json', json_encode($collection, $flags));
file_put_contents($dir . '/Developpement.postman_environment.json', json_encode(environment('Développement', 'http://localhost:8000/api/v1'), $flags));
file_put_contents($dir . '/Production.postman_environment.json', json_encode(environment('Production', 'https://api.mabibliotheque.com/v1'), $flags));

echo "3 fichiers générés dans : " . realpath($dir) . PHP_EOL;