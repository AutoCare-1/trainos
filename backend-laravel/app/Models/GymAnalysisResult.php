<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GymAnalysisResult extends Model
{
    use HasUuids;

    public $timestamps = false;
    protected $table = 'gym_analysis_results';

    protected $fillable = [
        'submission_id', 'machines_json', 'zones_identified', 'total_unique_machines',
        'coverage_estimate', 'gaps', 'notes',
    ];

    protected $casts = [
        'machines_json' => 'array',
        'zones_identified' => 'array',
        'gaps' => 'array',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(GymMediaSubmission::class, 'submission_id');
    }

    public function recommendation(): HasOne
    {
        return $this->hasOne(GymWorkoutRecommendation::class, 'analysis_result_id');
    }
}
