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
