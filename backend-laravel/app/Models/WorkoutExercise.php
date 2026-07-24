<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkoutExercise extends Model
{
    use HasUuids;

    public $timestamps = false;
    protected $table = 'workout_exercises';

    protected $fillable = [
        'workout_id', 'exercise_id', 'order_index', 'sets', 'reps',
        'load_kg', 'rest_seconds', 'notes', 'structure_type', 'group_label',
    ];

    public function workout(): BelongsTo
    {
        return $this->belongsTo(Workout::class);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    public function sessionEntries(): HasMany
    {
        return $this->hasMany(SessionEntry::class);
    }
}
