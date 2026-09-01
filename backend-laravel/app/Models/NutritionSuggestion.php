<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Orientação geral de pré/pós-treino pedida pelo aluno — ver App\Support\Nutricao. */
class NutritionSuggestion extends Model
{
    use HasUuids;

    public const MOMENTOS = ['pre_treino', 'pos_treino'];

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
