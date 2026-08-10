<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\WebPush\HasPushSubscriptions;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class Professional extends Model implements AuthenticatableContract, JWTSubject
{
    use HasUuids;
    use Authenticatable;
    use Notifiable;
    use HasPushSubscriptions;

    public $timestamps = false;
    protected $table = 'professionals';

    protected $fillable = ['name', 'email', 'password_hash'];

    protected $hidden = ['password_hash'];

    // is_admin fica fora de $fillable de propósito: acesso ao CRM só se concede
    // por forceFill em AdminCrmController, nunca por mass assignment vindo de um
    // payload de cadastro/edição de perfil.
    protected $casts = ['is_admin' => 'boolean'];

    // A coluna de senha do schema é password_hash, não password (nome padrão do Laravel).
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function workouts(): HasMany
    {
        return $this->hasMany(Workout::class);
    }

    public function workoutTemplates(): HasMany
    {
        return $this->hasMany(WorkoutTemplate::class);
    }

    public function challenges(): HasMany
    {
        return $this->hasMany(Challenge::class);
    }

    public function contentIdeas(): HasMany
    {
        return $this->hasMany(ContentIdea::class);
    }

    public function consultorIaMessages(): HasMany
    {
        return $this->hasMany(ConsultorIaMessage::class);
    }

    public function exerciseMediaOverrides(): HasMany
    {
        return $this->hasMany(ExerciseMediaOverride::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function gymMediaSubmissions(): HasMany
    {
        return $this->hasMany(GymMediaSubmission::class);
    }

    public function billingPlans(): HasMany
    {
        return $this->hasMany(StudentBillingPlan::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(ProfessionalExpense::class);
    }

    // JWTSubject — o pacote usa isto como claim `sub`, igual ao gerarToken() do Node
    // (jwt.sign({ sub: professionalId }, ...)).
    public function getJWTIdentifier(): string
    {
        return (string) $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }
}
