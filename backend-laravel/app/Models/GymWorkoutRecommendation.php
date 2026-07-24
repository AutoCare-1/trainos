<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GymWorkoutRecommendation extends Model
{
    use HasUuids;

    public $timestamps = false;
    protected $table = 'gym_workout_recommendations';

    protected $fillable = [
        'submission_id', 'analysis_result_id', 'name', 'split_type', 'reasoning',
        'recommended_items', 'approval_status', 'approved_workout_id', 'professional_notes', 'approved_at',
    ];

    protected $casts = [
        'recommended_items' => 'array',
        'approved_at' => 'datetime',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(GymMediaSubmission::class, 'submission_id');
    }

    public function analysisResult(): BelongsTo
    {
        return $this->belongsTo(GymAnalysisResult::class, 'analysis_result_id');
    }

    public function approvedWorkout(): BelongsTo
    {
        return $this->belongsTo(Workout::class, 'approved_workout_id');
    }
}
