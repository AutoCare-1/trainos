<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionEntry extends Model
{
    use HasUuids;

    public $timestamps = false;
    protected $table = 'session_entries';

    protected $fillable = ['training_session_id', 'workout_exercise_id', 'set_number', 'reps_done', 'load_kg_done', 'notes', 'is_pr', 'client_entry_id'];

    protected $casts = [
        'is_pr' => 'boolean',
    ];

    public function trainingSession(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class);
    }

    public function workoutExercise(): BelongsTo
    {
        return $this->belongsTo(WorkoutExercise::class);
    }
}
