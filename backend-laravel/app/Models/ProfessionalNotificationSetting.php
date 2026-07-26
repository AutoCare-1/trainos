<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfessionalNotificationSetting extends Model
{
    use HasUuids;

    protected $table = 'professional_notification_settings';

    protected $fillable = ['professional_id', 'tipo_chave', 'student_id', 'enabled'];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoNotificacao::class, 'tipo_chave', 'chave');
    }
}
