<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(ExerciseSeeder::class);
        // Depois do ExerciseSeeder de propósito: a ampliação só insere nomes que
        // ainda não existem, então os 75 originais precisam estar no banco antes
        // pra manterem a foto real deles.
        $this->call(ExercicioBibliotecaAmpliadaSeeder::class);
        $this->call(NotificationTypesSeeder::class);
    }
}
