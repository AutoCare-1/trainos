<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Orientação geral de pré/pós-treino pedida pelo aluno — ver App\Support\Nutricao. */
class NutritionSuggestion extends Model
{
    use HasUuids;

    /**
     * Momentos sobre os quais o aluno pode pedir orientação.
     *
     * Pré/pós-treino nasceram primeiro por serem os mais ligados ao treino —
     * território direto de quem acompanha. As refeições do dia entraram
     * depois, e valem a mesma regra: orientação geral e educativa, nunca "seu
     * café da manhã deve ser X". Ver App\Support\Nutricao.
     */
    public const MOMENTOS = ['pre_treino', 'pos_treino', 'cafe', 'almoco', 'jantar'];

    public $timestamps = false;

    protected $fillable = ['student_id', 'momento', 'resposta', 'encaminhou_nutricionista'];

    protected $casts = [
        'encaminhou_nutricionista' => 'boolean',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
