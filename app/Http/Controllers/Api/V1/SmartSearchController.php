<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Exceptions\OpenAIUnavailableException;
use App\Http\Requests\SmartSearchRequest;
use App\Models\Book;
use App\Services\OpenAIService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class SmartSearchController extends Controller
{
    use ApiResponse;
    //
    public function __construct(private OpenAIService $openai)
    {

    }

    // genere le resumé d'un livre
    public function __invoke(SmartSearchRequest $request): JsonResponse
    {
        $query = $request->validated('query');
        try {
            $keywords = $this->openai->extractKeywords($query);
        } catch (OpenAIUnavailableException $e) {
            return $this->error('Service OpenAI indisponible', null, 503);
        }

        $books = [];
        if (! empty($keywords)) {
            $books = Book::with('author')
                ->where(function ($q) use ($keywords) {
                    foreach ($keywords as $keyword) {
                        $like = '%' . addcslashes($keyword, '%_\\') . '%';
                        $q->orWhere('title', 'like', $like)
                            ->orWhere('summary', 'like', $like)
                            ->orWhereHas('author', function ($a) use ($like) {
                                $a->where('first_name', 'like', $like)
                                    ->orWhere('last_name', 'like', $like);
                            });
                    }
                })
                ->get();
        }

        // Format
        return response()->json([
            'success' => true,
            'query' => $query,
            'keywords' => $keywords,
            'data' => $books,
        ]);
    }
}
