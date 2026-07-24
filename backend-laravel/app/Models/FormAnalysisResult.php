<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormAnalysisResult extends Model
{
    use HasUuids;

    public $timestamps = false;
    protected $table = 'form_analysis_results';

    protected $fillable = [
        'video_id', 'amplitude_assessment', 'posture_assessment', 'tempo_assessment',
        'compensations', 'safety_notes', 'three_key_feedback', 'analysis_status',
    ];

    protected $casts = [
        'three_key_feedback' => 'array',
    ];

    public function video(): BelongsTo
    {
        return $this->belongsTo(FormCorrectionVideo::class, 'video_id');
    }
}
