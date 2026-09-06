<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Uma medida caseira de um alimento ("Concha", 140 g) — ver a migration. */
class FoodMeasure extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['food_id', 'nome', 'gramas'];

    protected $casts = ['gramas' => 'float'];

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }
}
