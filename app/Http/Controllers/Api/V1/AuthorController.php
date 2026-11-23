<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\StoreAuthorRequest;
use App\Http\Requests\UpdateAuthorRequest;
use App\Models\Author;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class AuthorController extends Controller
{
    //
    use ApiResponse;

    public function index(): JsonResponse
    {
        return $this->success(Author::all(), 'Liste des auteurs');
    }

    public function store(StoreAuthorRequest $request): JsonResponse
    {
        $author = Author::create($request->validated());

        return $this->success($author, 'Auteur créé avec succès', 201);
    }

    public function show(int $id): JsonResponse
    {
        $author = Author::with('books')->find($id);
        if (! $author) {
            return $this->error('Auteur introuvable', null, 404);
        }

        return $this->success($author, 'Auteur récupéré avec succès');
    }

    public function update(UpdateAuthorRequest $request, int $id): JsonResponse
    {
        $author = Author::find($id);
        if (! $author) {
            return $this->error('Auteur introuvable', null, 404);
        }

        $author->update($request->validated());
        return $this->success($author->fresh(), 'Auteur modifié avec succès');
    }

    public function destroy(int $id): JsonResponse
    {
        $author = Author::find($id);
        if (! $author) {
            return $this->error('Auteur introuvable', null, 404);
        }

        $author->delete();
        return response()->json(null, 204);
    }
}
