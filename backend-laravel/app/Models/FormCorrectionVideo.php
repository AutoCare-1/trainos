<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FormCorrectionVideo extends Model
{
    use HasUuids;

    public $timestamps = false;
    protected $table = 'form_correction_videos';

    protected $fillable = ['student_id', 'workout_id', 'exercise_id', 'video_file_path', 'video_duration_seconds'];

    protected $casts = [
        'video_duration_seconds' => 'decimal:2',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function workout(): BelongsTo
    {
        return $this->belongsTo(Workout::class);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    public function analysisResult(): HasOne
    {
        return $this->hasOne(FormAnalysisResult::class, 'video_id');
    }
}
