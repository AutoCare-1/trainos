<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /** Medidas caseiras conhecidas — pode ser vazio (ver FoodMeasure). */
    public function medidas(): HasMany
    {
        return $this->hasMany(FoodMeasure::class);
    }

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
