<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgendaSlot extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'professional_id', 'student_id', 'titulo', 'dia_semana', 'hora', 'duracao_minutos', 'ativo',
    ];

    protected $casts = [
        'dia_semana' => 'integer',
        'duracao_minutos' => 'integer',
        'ativo' => 'boolean',
    ];

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function ocorrencias(): HasMany
    {
        return $this->hasMany(AgendaOcorrencia::class, 'slot_id');
    }
}
