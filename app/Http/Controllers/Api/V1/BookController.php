<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class BookController extends Controller
{
    //
    use ApiResponse;
    public function index(Request $request): JsonResponse
    {
        $query = Book::with('author');
        if ($request->has('available')) {
            $available = filter_var($request->query('available'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($available === null) {
                return $this->error(
                    'Paramètre invalide',
                    ['available' => ['Le paramètre available doit valoir true ou false.']],
                    422
                );
            }

            $query->where('available', $available);
        }

        return $this->success($query->get(), 'Liste des livres');
    }

    public function store(StoreBookRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['available'] = $data['available'] ?? true;
        $book = Book::create($data);
        return $this->success($book->load('author'), 'Livre ajouté avec succès', 201);
    }

    public function show(int $id): JsonResponse
    {
        $book = Book::with('author')->find($id);
        if (! $book) {
            return $this->error('Livre introuvable', null, 404);
        }

        return $this->success($book, 'Livre récupéré avec succès');
    }

    public function update(UpdateBookRequest $request, int $id): JsonResponse
    {
        $book = Book::find($id);
        if (! $book) {
            return $this->error('Livre introuvable', null, 404);
        }

        $book->update($request->validated());
        return $this->success($book->fresh('author'), 'Livre modifié avec succès');
    }

    public function destroy(int $id): JsonResponse
    {
        $book = Book::find($id);
        if (! $book) {
            return $this->error('Livre introuvable', null, 404);
        }

        $book->delete();
        return response()->json(null, 204);
    }
}
