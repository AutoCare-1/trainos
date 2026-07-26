<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentBillingPlan extends Model
{
    use HasUuids;

    // Lista fixa (não enum do MySQL) — adicionar um tipo novo é só incluir aqui,
    // sem migration. Referenciada pelos Form Requests de aluno.
    public const TIPOS_VALIDOS = ['consultoria', 'presencial'];

    protected $fillable = [
        'student_id', 'professional_id', 'billing_type', 'monthly_value', 'starts_on', 'ends_on',
    ];

    protected $casts = [
        'monthly_value' => 'decimal:2',
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }
}
