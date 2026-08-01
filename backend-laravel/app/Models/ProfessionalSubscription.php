<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProfessionalSubscription extends Model
{
    use HasUuids;

    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_ATIVA = 'ativa';

    public const STATUS_ATRASADA = 'atrasada';

    public const STATUS_BLOQUEADA = 'bloqueada';

    public const STATUS_CANCELADA = 'cancelada';

    public $timestamps = false;

    protected $fillable = [
        'professional_id', 'plano_chave', 'status', 'mp_preapproval_id', 'proxima_cobranca_em', 'atraso_desde',
    ];

    protected $casts = [
        'proxima_cobranca_em' => 'date',
        'atraso_desde' => 'date',
    ];

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ProfessionalSubscriptionPayment::class, 'subscription_id');
    }
}
