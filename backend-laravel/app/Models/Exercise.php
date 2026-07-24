<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exercise extends Model
{
    use HasUuids;

    public $timestamps = false;
    protected $table = 'exercises';

    protected $fillable = ['name', 'muscle_group', 'equipment', 'instructions', 'video_url', 'image_url', 'image_credit'];

    public function workoutExercises(): HasMany
    {
        return $this->hasMany(WorkoutExercise::class);
    }

    public function workoutTemplateExercises(): HasMany
    {
        return $this->hasMany(WorkoutTemplateExercise::class);
    }

    public function mediaOverrides(): HasMany
    {
        return $this->hasMany(ExerciseMediaOverride::class);
    }

    public function formCorrectionVideos(): HasMany
    {
        return $this->hasMany(FormCorrectionVideo::class);
    }

    public function formFeedbackHistories(): HasMany
    {
        return $this->hasMany(FormFeedbackHistory::class);
    }
}
