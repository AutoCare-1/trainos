<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfessionalSubscriptionPayment extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['subscription_id', 'mp_payment_id', 'valor', 'status', 'pago_em'];

    protected $casts = [
        'valor' => 'decimal:2',
        'pago_em' => 'date',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(ProfessionalSubscription::class, 'subscription_id');
    }
}
