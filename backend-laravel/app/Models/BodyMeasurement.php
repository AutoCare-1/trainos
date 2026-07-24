<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BodyMeasurement extends Model
{
    use HasUuids;

    public $timestamps = false;
    protected $table = 'body_measurements';

    protected $fillable = ['student_id', 'recorded_at', 'weight_kg', 'waist_cm', 'hip_cm', 'body_fat_pct', 'notes'];

    protected $casts = [
        'recorded_at' => 'date',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
