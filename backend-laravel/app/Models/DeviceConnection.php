<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceConnection extends Model
{
    use HasUuids;

    public $timestamps = false;
    protected $table = 'device_connections';

    protected $fillable = [
        'student_id', 'provider', 'provider_athlete_id', 'access_token',
        'refresh_token', 'expires_at', 'scope',
    ];

    protected $hidden = ['access_token', 'refresh_token'];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
