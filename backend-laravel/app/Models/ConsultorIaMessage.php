<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsultorIaMessage extends Model
{
    use HasUuids;

    public $timestamps = false;
    protected $table = 'consultor_ia_messages';

    protected $fillable = ['professional_id', 'role', 'content'];

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }
}
