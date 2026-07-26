<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ChallengeRankSnapshot extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'challenge_rank_snapshots';

    protected $fillable = ['challenge_id', 'student_id', 'posicao', 'updated_at'];

    protected $casts = [
        'updated_at' => 'datetime',
    ];
}
