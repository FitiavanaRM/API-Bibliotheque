<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Exceptions\OpenAIUnavailableException;
use App\Models\Book;
use App\Services\OpenAIService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class BookSummaryController extends Controller
{
    use ApiResponse;
    //
    public function __construct(private OpenAIService $openai)
    {

    }

    public function store(int $id): JsonResponse
    {
        $book = Book::with('author')->find($id);

        if (! $book) {
            return $this->error('Livre introuvable', null, 404);
        }

        // resumé déjà existant
        if (! empty($book->summary)) {
            return $this->success($this->payload($book), 'Résumé déjà existant');
        }

        try {
            $summary = $this->openai->generateSummary($book);
        } catch (OpenAIUnavailableException $e) {
            return $this->error('Service OpenAI indisponible', null, 503);
        }

        $book->update([
            'summary' => $summary,
            'summary_generated_by' => $this->openai->model(),
            'summary_generated_at' => now(),
        ]);

        return $this->success($this->payload($book->fresh()), 'Résumé généré avec succès');
    }

    // lit le resumé
    public function show(int $id): JsonResponse
    {
        $book = Book::find($id);

        if (! $book) {
            return $this->error('Livre introuvable', null, 404);
        }

        if (empty($book->summary)) {
            return $this->error(
                'Aucun résumé disponible pour ce livre',
                ['summary' => ['Générez-le d\'abord avec POST /books/' . $book->id . '/summary.']],
                404
            );
        }

        return $this->success($this->payload($book), 'Résumé récupéré avec succès');
    }

    private function payload(Book $book): array
    {
        return [
            'book_id' => $book->id,
            'title' => $book->title,
            'summary' => $book->summary,
            'generated_by' => $book->summary_generated_by,
            'generated_at' => $book->summary_generated_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}