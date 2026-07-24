<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalActivity extends Model
{
    use HasUuids;

    public $timestamps = false;
    protected $table = 'external_activities';

    protected $fillable = [
        'student_id', 'provider', 'external_id', 'activity_type', 'name', 'started_at',
        'duration_seconds', 'distance_meters', 'calories', 'avg_heart_rate', 'raw_payload',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'raw_payload' => 'array',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
