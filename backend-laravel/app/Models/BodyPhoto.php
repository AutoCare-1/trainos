<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BodyPhoto extends Model
{
    use HasUuids;

    public $timestamps = false;
    protected $table = 'body_photos';

    protected $fillable = ['student_id', 'file_path', 'taken_at', 'ai_feedback', 'compared_to_photo_id'];

    protected $casts = [
        'taken_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function comparedToPhoto(): BelongsTo
    {
        return $this->belongsTo(BodyPhoto::class, 'compared_to_photo_id');
    }
}
