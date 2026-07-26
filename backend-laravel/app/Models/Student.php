<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use HasUuids;

    public $timestamps = false;
    protected $table = 'students';

    protected $fillable = [
        'professional_id', 'name', 'email', 'phone', 'objective',
        'weight_kg', 'height_cm', 'invite_token', 'status', 'ai_autopilot',
        'par_q_answers', 'health_notes', 'onboarding_completed_at', 'photo_url',
    ];

    protected $casts = [
        'ai_autopilot' => 'boolean',
        'par_q_answers' => 'array',
        'weight_kg' => 'decimal:2',
        'height_cm' => 'decimal:2',
        'onboarding_completed_at' => 'datetime',
    ];

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function workouts(): HasMany
    {
        return $this->hasMany(Workout::class);
    }

    public function bodyMeasurements(): HasMany
    {
        return $this->hasMany(BodyMeasurement::class);
    }

    public function deviceConnections(): HasMany
    {
        return $this->hasMany(DeviceConnection::class);
    }

    public function externalActivities(): HasMany
    {
        return $this->hasMany(ExternalActivity::class);
    }

    public function bodyPhotos(): HasMany
    {
        return $this->hasMany(BodyPhoto::class);
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(Checkin::class);
    }

    public function challengeParticipants(): HasMany
    {
        return $this->hasMany(ChallengeParticipant::class);
    }

    public function gymMediaSubmissions(): HasMany
    {
        return $this->hasMany(GymMediaSubmission::class);
    }

    public function formCorrectionVideos(): HasMany
    {
        return $this->hasMany(FormCorrectionVideo::class);
    }

    public function formFeedbackHistories(): HasMany
    {
        return $this->hasMany(FormFeedbackHistory::class);
    }

    public function trainingSessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class);
    }

    public function billingPlans(): HasMany
    {
        return $this->hasMany(StudentBillingPlan::class);
    }
}
