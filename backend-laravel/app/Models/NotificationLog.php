<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'notification_logs';

    protected $fillable = ['tipo_chave', 'student_id', 'professional_id', 'contexto', 'dedup_key'];

    protected $casts = [
        'enviado_em' => 'datetime',
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
