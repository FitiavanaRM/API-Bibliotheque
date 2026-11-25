<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AuthorController;
use App\Http\Controllers\Api\V1\BookController;
use App\Http\Controllers\Api\V1\BorrowController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\BookSummaryController;
use App\Http\Controllers\Api\V1\SmartSearchController;

// Les identifiants dans l'URL doivent être numériques (sinon 404)
Route::pattern('id', '[0-9]+');

Route::prefix('v1')->group(function () {

    // ---------- Routes publiques ----------
    Route::post('auth/login', [AuthController::class, 'login']);

    Route::get('authors', [AuthorController::class, 'index']);
    Route::get('authors/{id}', [AuthorController::class, 'show']);

    Route::get('books', [BookController::class, 'index']);
    Route::get('books/{id}', [BookController::class, 'show']);
    Route::get('books/{id}/summary', [BookSummaryController::class, 'show']);

    // ---------- Routes protégées (Bearer token) ----------
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('authors', [AuthorController::class, 'store']);
        Route::put('authors/{id}', [AuthorController::class, 'update']);
        Route::delete('authors/{id}', [AuthorController::class, 'destroy']);

        Route::post('books', [BookController::class, 'store']);
        Route::post('books/smart-search', SmartSearchController::class);
        Route::post('books/{id}/summary', [BookSummaryController::class, 'store']);
        Route::put('books/{id}', [BookController::class, 'update']);
        Route::delete('books/{id}', [BookController::class, 'destroy']);

        Route::get('borrows', [BorrowController::class, 'index']);
        Route::post('borrows', [BorrowController::class, 'store']);
        Route::patch('borrows/{id}/return', [BorrowController::class, 'returnBook']);
    });
});

// ---------- Documentation Swagger UI ----------
Route::view('docs', 'swagger');