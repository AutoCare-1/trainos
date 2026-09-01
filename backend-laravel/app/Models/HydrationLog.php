<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Copos de água por dia (uma linha por aluno/dia). */
class HydrationLog extends Model
{
    use HasUuids;

    /** Teto por dia em ml: acima de 8 L é engano de toque, não hidratação. */
    public const MAX_ML = 8000;

    /** Volumes que um toque pode somar (ou tirar). O servidor manda aqui, não o cliente. */
    public const VOLUMES = ['copo' => 200, 'garrafa' => 500];

    /**
     * Referência diária em ml a partir do peso (~35 ml por kg, a regra de bolso
     * mais usada). Não é prescrição: é a mesma orientação geral de hidratação
     * que qualquer professor dá — o que não pode é montar dieta.
     *
     * Sem peso informado, o padrão é o que a própria fórmula daria pra um
     * adulto médio (~72 kg), e não os clássicos 2 L — que são herança de
     * ditado e ficam baixos pra quem treina e sua. Assim o padrão deixa de ser
     * número escolhido no olho.
     *
     * Não subimos mais que isso de propósito: a barra é o mecanismo da
     * feature, e meta que não enche nunca faz o aluno parar de registrar — aí
     * perde-se o dado, que é pior que a meta ficar um pouco baixa.
     *
     * Os limites existem pra peso digitado errado (um "7" no lugar de "70",
     * ou um 300) não gerar meta absurda.
     */
    public const META_PADRAO_ML = 2500;

    private const ML_POR_KG = 35;

    private const META_MINIMA_ML = 1500;

    private const META_MAXIMA_ML = 4500;

    public static function metaDiariaMl(?float $pesoKg): int
    {
        if (! $pesoKg || $pesoKg <= 0) {
            return self::META_PADRAO_ML;
        }

        $bruta = (int) round($pesoKg * self::ML_POR_KG / 100) * 100; // arredonda de 100 em 100

        return max(self::META_MINIMA_ML, min(self::META_MAXIMA_ML, $bruta));
    }

    public $timestamps = false;

    protected $fillable = ['student_id', 'data', 'ml'];

    protected $casts = [
        // date:Y-m-d e não 'date': é data pura, e sem o formato o Eloquent
        // serializa como ISO completo ("...T03:00:00Z"), que quebra os
        // helpers de data do frontend — eles fatiam a string de propósito,
        // pra não deslocar o dia por fuso.
        'data' => 'date:Y-m-d',
        'ml' => 'integer',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
