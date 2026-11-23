<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Author;
use Illuminate\Database\Seeder;

class AuthorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $authors = [
            ['first_name' => 'Victor', 'last_name' => 'Hugo', 'nationality' => 'Française'],
            ['first_name' => 'Albert', 'last_name' => 'Camus', 'nationality' => 'Française'],
            ['first_name' => 'Gabriel', 'last_name' => 'García Márquez', 'nationality' => 'Colombienne'],
        ];

        foreach ($authors as $author) {
            Author::create($author);
        }
    }
}
