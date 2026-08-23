<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgendaOcorrencia extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['slot_id', 'data', 'student_id', 'titulo', 'presenca', 'observacao'];

    protected $casts = [
        'data' => 'date',
    ];

    public function slot(): BelongsTo
    {
        return $this->belongsTo(AgendaSlot::class, 'slot_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
