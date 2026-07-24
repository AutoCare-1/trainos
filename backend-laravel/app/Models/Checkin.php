<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Checkin extends Model
{
    use HasUuids;

    public $timestamps = false;
    protected $table = 'checkins';

    protected $fillable = ['student_id', 'checkin_date', 'file_path', 'comment'];

    protected $casts = [
        'checkin_date' => 'date',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
