<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormFeedbackHistory extends Model
{
    use HasUuids;

    public $timestamps = false;
    protected $table = 'form_feedback_history';

    protected $fillable = ['student_id', 'exercise_id', 'feedback_count', 'last_feedback_at'];

    protected $casts = [
        'last_feedback_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
