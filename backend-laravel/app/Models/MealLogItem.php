<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Um alimento escolhido numa refeição do diário — ver a migration. */
class MealLogItem extends Model
{
    use HasUuids;

    /** Teto por item: acima de 2 kg é engano de digitação, não porção. */
    public const MAX_QUANTIDADE_G = 2000;

    public $timestamps = false;

    protected $fillable = ['meal_log_id', 'food_id', 'quantidade_g', 'medida_nome'];

    protected $casts = ['quantidade_g' => 'integer'];

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }

    public function mealLog(): BelongsTo
    {
        return $this->belongsTo(MealLog::class);
    }
}
