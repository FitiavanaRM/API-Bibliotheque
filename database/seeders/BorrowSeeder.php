<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Book;
use App\Models\Borrow;
use Illuminate\Database\Seeder;

class BorrowSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $loans = [
            ['user_name' => 'Rakoto Jean', 'isbn' => '9782070360024', 'days_ago' => 5],
            ['user_name' => 'Rasoa Marie', 'isbn' => '9782020238113', 'days_ago' => 2],
        ];

        foreach ($loans as $loan) {
            $book = Book::where('isbn', $loan['isbn'])->first();

            Borrow::create([
                'user_name' => $loan['user_name'],
                'book_id' => $book->id,
                'borrowed_at' => now()->subDays($loan['days_ago']),
                'returned_at' => null,
            ]);

            $book->update(['available' => false]);
        }
    }
}
