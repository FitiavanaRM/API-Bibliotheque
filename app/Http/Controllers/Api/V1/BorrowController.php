<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBorrowRequest;
use App\Models\Book;
use App\Models\Borrow;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BorrowController extends Controller
{
    //
    use ApiResponse;

    /**
     * Emprunts en cours (returned_at est null).
     */
    public function index(Request $request): JsonResponse
    {
        $borrows = $request->user()->borrows()
            ->with('book.author')
            ->whereNull('returned_at')
            ->get();

        return $this->success($borrows, 'Liste des emprunts en cours');
    }

    public function store(StoreBorrowRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $paymentStatus = $user->isPremium() ? 'paid' : 'free';

        $result = DB::transaction(function () use ($validated, $user, $paymentStatus) {
            $book = Book::lockForUpdate()->find($validated['book_id']);

            if (! $book->available) {
                return null;
            }

            $borrow = Borrow::create([
                'user_name' => $validated['user_name'],
                'book_id' => $book->id,
                'user_id' => $user->id,
                'borrowed_at' => now(),
                'returned_at' => null,
                'payment_status' => $paymentStatus,
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

    public function returnBook(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $borrow = $user->borrows()->find($id);

        if (! $borrow) {
            return $this->error('Emprunt introuvable', null, 404);
        }

        $alreadyReturned = DB::transaction(function () use ($user, $borrow) {
            $borrow = $user->borrows()->lockForUpdate()->find($borrow->id);

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
