<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Alimento da TACO (NEPA/UNICAMP). Valores por 100 g — ver a migration.
 *
 * Somente leitura na prática: a tabela vem do seeder e ninguém edita pelo app.
 */
class Food extends Model
{
    use HasUuids;

    public $timestamps = false;

    // Explícito porque o Eloquent pluraliza "Food" como "food" (substantivo
    // incontável em inglês) e a tabela é "foods".
    protected $table = 'foods';

    protected $fillable = [
        'codigo_taco', 'categoria', 'nome',
        'kcal', 'proteina_g', 'carboidrato_g', 'lipideos_g', 'fibra_g',
    ];

    protected $casts = [
        'codigo_taco' => 'integer',
        // float e não decimal:2 no cast porque o cast decimal do Eloquent
        // devolve string; aqui o valor é somado e comparado.
        'kcal' => 'float',
        'proteina_g' => 'float',
        'carboidrato_g' => 'float',
        'lipideos_g' => 'float',
        'fibra_g' => 'float',
    ];
}
