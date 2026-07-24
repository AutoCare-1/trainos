<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExerciseMediaOverride extends Model
{
    use HasUuids;

    public $timestamps = false;
    protected $table = 'exercise_media_overrides';

    protected $fillable = ['professional_id', 'exercise_id', 'video_url'];

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
