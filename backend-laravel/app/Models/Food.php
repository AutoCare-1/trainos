<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

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
        'codigo_taco', 'categoria', 'nome', 'nome_busca',
        'kcal', 'proteina_g', 'carboidrato_g', 'lipideos_g', 'fibra_g',
    ];

    /**
     * Como nome e termo de busca são comparados: sem acento, em minúscula.
     *
     * Mora aqui, e não no controller, porque quem grava (o seeder) e quem
     * consulta (a busca) precisam normalizar do mesmo jeito — se as duas
     * pontas divergirem, a busca simplesmente para de achar.
     */
    public static function normalizarParaBusca(string $texto): string
    {
        return Str::ascii(mb_strtolower(trim($texto)));
    }

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
