<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Registro de refeição do diário alimentar — ver a migration pra o porquê do desenho. */
class MealLog extends Model
{
    use HasUuids;

    /** Momentos que o aluno pode escolher ao registrar. */
    public const MOMENTOS = ['cafe', 'lanche', 'almoco', 'jantar', 'pre_treino', 'pos_treino'];

    public $timestamps = false;

    protected $fillable = ['student_id', 'data', 'momento', 'file_path', 'descricao'];

    protected $casts = [
        // date:Y-m-d e não 'date': é data pura, e sem o formato o Eloquent
        // serializa como ISO completo ("...T03:00:00Z"), que quebra os
        // helpers de data do frontend — eles fatiam a string de propósito,
        // pra não deslocar o dia por fuso.
        'data' => 'date:Y-m-d',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
