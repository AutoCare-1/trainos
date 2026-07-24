<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GymMediaSubmission extends Model
{
    use HasUuids;

    public $timestamps = false;
    protected $table = 'gym_media_submissions';

    protected $fillable = ['student_id', 'professional_id', 'submission_type', 'days_per_week', 'status', 'error_message'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(GymMediaAsset::class, 'submission_id');
    }

    public function analysisResult(): HasOne
    {
        return $this->hasOne(GymAnalysisResult::class, 'submission_id');
    }

    public function recommendation(): HasOne
    {
        return $this->hasOne(GymWorkoutRecommendation::class, 'submission_id');
    }
}
