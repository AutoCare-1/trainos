<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkoutReview extends Model
{
    use HasUuids;

    public $timestamps = false;
    protected $table = 'workout_reviews';

    protected $fillable = ['student_id', 'workout_id', 'tempo_acompanhamento_semanas', 'respostas'];

    protected $casts = [
        'respostas' => 'array',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function workout(): BelongsTo
    {
        return $this->belongsTo(Workout::class);
    }
}
