<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    use HasUuids;

    public $timestamps = false;
    protected $table = 'feedbacks';

    protected $fillable = ['training_session_id', 'effort_rpe', 'satisfaction', 'discomfort', 'comment'];

    public function trainingSession(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class);
    }
}
