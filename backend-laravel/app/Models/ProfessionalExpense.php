<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Despesa do personal pra operar o negócio (aluguel de sala, software, etc).
 *
 * Quando a cobrança da assinatura do TrainOS pro personal existir (responsabilidade
 * de outra equipe/sistema), dá pra popular aqui uma despesa recorrente automática
 * ("Assinatura TrainOS") em vez do personal digitar manualmente — a estrutura já é
 * compatível (is_recurring=true, sem ends_on), só não está implementado ainda.
 */
class ProfessionalExpense extends Model
{
    use HasUuids;

    protected $fillable = [
        'professional_id', 'description', 'amount', 'is_recurring', 'starts_on', 'ends_on', 'previous_expense_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_recurring' => 'boolean',
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    public function previousExpense(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_expense_id');
    }
}
