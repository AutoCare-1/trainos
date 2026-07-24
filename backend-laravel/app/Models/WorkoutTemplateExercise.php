<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkoutTemplateExercise extends Model
{
    use HasUuids;

    public $timestamps = false;
    protected $table = 'workout_template_exercises';

    protected $fillable = [
        'template_id', 'exercise_id', 'order_index', 'sets', 'reps',
        'load_kg', 'rest_seconds', 'notes', 'structure_type', 'group_label',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkoutTemplate::class, 'template_id');
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
