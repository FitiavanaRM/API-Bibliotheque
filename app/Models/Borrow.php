<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Borrow extends Model
{
    //
    protected $fillable = [
        'user_name',
        'book_id',
        'borrowed_at',
        'returned_at',
    ];

    protected function casts(): array
    {
        return [
            'borrowed_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    /**
     * Un emprunt appartient à un seul livre.
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
