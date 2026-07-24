<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TrendCache extends Model
{
    use HasUuids;

    public $timestamps = false;
    protected $table = 'trend_cache';

    protected $fillable = ['content_snapshot'];

    protected $casts = [
        'cached_at' => 'datetime',
    ];
}
