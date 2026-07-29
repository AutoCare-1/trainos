<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workout extends Model
{
    use HasUuids;

    protected $table = 'workouts';

    protected $fillable = [
        'professional_id', 'student_id', 'name', 'status', 'sent_at',
        'duration_weeks', 'expires_at', 'archived_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'expires_at' => 'date:Y-m-d',
        'archived_at' => 'datetime',
    ];

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(WorkoutExercise::class);
    }

    public function trainingSessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class);
    }

    public function formCorrectionVideos(): HasMany
    {
        return $this->hasMany(FormCorrectionVideo::class);
    }
}
