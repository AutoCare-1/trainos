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
        // Terceira leva (esportivo, mobilidade, alongamento, ativação, equilíbrio,
        // prevenção). Também só insere nome inédito, e por isso vem depois das
        // duas anteriores: elas é que são donas dos nomes que compartilham.
        $this->call(ExercicioBibliotecaComplementarSeeder::class);
        $this->call(NotificationTypesSeeder::class);
    }
}
