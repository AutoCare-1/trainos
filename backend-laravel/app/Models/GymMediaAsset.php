<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GymMediaAsset extends Model
{
    use HasUuids;

    public $timestamps = false;
    protected $table = 'gym_media_assets';

    protected $fillable = ['submission_id', 'asset_type', 'file_path', 'frame_index'];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(GymMediaSubmission::class, 'submission_id');
    }
}
