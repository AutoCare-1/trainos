<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosturalAssessment extends Model
{
    use HasUuids;

    public $timestamps = false;
    protected $table = 'postural_assessments';

    protected $fillable = [
        'student_id', 'front_photo_path', 'side_photo_path', 'back_photo_path',
        'taken_at', 'ai_feedback', 'compared_to_assessment_id',
    ];

    protected $casts = [
        'taken_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function comparedToAssessment(): BelongsTo
    {
        return $this->belongsTo(PosturalAssessment::class, 'compared_to_assessment_id');
    }
}
