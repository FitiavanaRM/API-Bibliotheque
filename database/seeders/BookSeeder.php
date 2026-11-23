<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Author;
use App\Models\Book;
use Illuminate\Database\Seeder;

class BookSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $hugo = Author::where('last_name', 'Hugo')->first();
        $camus = Author::where('last_name', 'Camus')->first();
        $garcia = Author::where('last_name', 'García Márquez')->first();

        $books = [
            ['title' => 'Les Misérables', 'isbn' => '9782070409228', 'year' => 1862, 'author_id' => $hugo->id],
            ['title' => 'Notre-Dame de Paris', 'isbn' => '9782070413096', 'year' => 1831, 'author_id' => $hugo->id],
            ['title' => "L'Étranger", 'isbn' => '9782070360024', 'year' => 1942, 'author_id' => $camus->id],
            ['title' => 'La Peste', 'isbn' => '9782070360420', 'year' => 1947, 'author_id' => $camus->id],
            ['title' => 'Cent ans de solitude', 'isbn' => '9782020238113', 'year' => 1967, 'author_id' => $garcia->id],
        ];

        foreach ($books as $book) {
            Book::create($book + ['available' => true]);
        }
    }
}
