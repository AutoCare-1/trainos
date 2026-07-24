<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentIdea extends Model
{
    use HasUuids;

    public $timestamps = false;
    protected $table = 'content_ideas';

    protected $fillable = [
        'professional_id', 'batch_id', 'format', 'title', 'description', 'caption_suggestion', 'saved',
    ];

    protected $casts = [
        'saved' => 'boolean',
    ];

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }
}
