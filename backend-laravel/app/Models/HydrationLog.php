<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Copos de água por dia (uma linha por aluno/dia). */
class HydrationLog extends Model
{
    use HasUuids;

    /** Teto por dia: acima disso é engano de toque, não hidratação. */
    public const MAX_COPOS = 30;

    public $timestamps = false;

    protected $fillable = ['student_id', 'data', 'copos'];

    protected $casts = [
        'data' => 'date',
        'copos' => 'integer',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
