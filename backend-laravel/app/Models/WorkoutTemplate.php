<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkoutTemplate extends Model
{
    use HasUuids;

    public $timestamps = false;
    protected $table = 'workout_templates';

    protected $fillable = ['professional_id', 'name'];

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(WorkoutTemplateExercise::class, 'template_id');
    }
}
