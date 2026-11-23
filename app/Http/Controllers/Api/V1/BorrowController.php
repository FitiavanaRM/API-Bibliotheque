<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\StoreBorrowRequest;
use App\Models\Book;
use App\Models\Borrow;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class BorrowController extends Controller
{
    //
    use ApiResponse;

    /**
     * Emprunts en cours (returned_at est null).
     */
    public function index(): JsonResponse
    {
        $borrows = Borrow::with('book.author')
            ->whereNull('returned_at')
            ->get();

        return $this->success($borrows, 'Liste des emprunts en cours');
    }

    public function store(StoreBorrowRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $result = DB::transaction(function () use ($validated) {
            $book = Book::lockForUpdate()->find($validated['book_id']);
            if (! $book->available) {
                return null;
            }

            $borrow = Borrow::create([
                'user_name' => $validated['user_name'],
                'book_id' => $book->id,
                'borrowed_at' => now(),
                'returned_at' => null,
            ]);

            $book->update(['available' => false]);
            return $borrow;
        });

        if ($result === null) {
            return $this->error(
                'Livre indisponible',
                ['book_id' => ['Ce livre est déjà emprunté et ne peut pas être emprunté.']],
                422
            );
        }

        return $this->success($result->load('book.author'), 'Emprunt enregistré avec succès', 201);
    }

    public function returnBook(int $id): JsonResponse
    {
        $borrow = Borrow::find($id);
        if (! $borrow) {
            return $this->error('Emprunt introuvable', null, 404);
        }

        $alreadyReturned = DB::transaction(function () use ($borrow) {
            $borrow = Borrow::lockForUpdate()->find($borrow->id);

            if ($borrow->returned_at !== null) {
                return true;
            }

            $borrow->update(['returned_at' => now()]);
            $borrow->book()->update(['available' => true]);
            return false;
        });

        if ($alreadyReturned) {
            return $this->error(
                'Emprunt déjà retourné',
                ['borrow' => ['Ce livre a déjà été rendu.']],
                422
            );
        }

        return $this->success($borrow->fresh('book.author'), 'Livre retourné avec succès');
    }
}
