<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    //
    protected $fillable = [
        'title',
        'isbn',
        'year',
        'available',
        'author_id',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'available' => 'boolean',
        ];
    }

    /**
     * Un livre appartient à un auteur.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    /**
     * Un livre peut avoir plusieurs emprunts au cours du temps.
     */
    public function borrows(): HasMany
    {
        return $this->hasMany(Borrow::class);
    }
}
