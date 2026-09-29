<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $rol
 * @property bool $activo
 * @property string $estado_cuenta
 * @property int $sesion_version
 * @property int $verification_version
 * @property bool $debe_cambiar_password
 * @property bool|null $cuenta_provisional
 * @property string|null $motivo_inactivacion
 * @property string|null $nombres
 * @property string|null $apellidos
 * @property string|null $dni
 * @property string|null $celular
 * @property int|null $cargo_institucional_id
 * @property string|null $correo_alternativo
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'rol', 'activo', 'estado_cuenta', 'nombres', 'apellidos', 'dni', 'celular', 'cargo_institucional_id', 'correo_alternativo', 'debe_cambiar_password', 'cuenta_provisional'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
            'sesion_version' => 'integer',
            'verification_version' => 'integer',
            'debe_cambiar_password' => 'boolean',
            'cuenta_provisional' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /** @return HasMany<Tramite, $this> */
    public function tramitesPropios(): HasMany
    {
        return $this->hasMany(Tramite::class, 'propietario_id');
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->increment('verification_version');
        $this->refresh();
        $this->notify(new VerifyEmail);
    }

    /** @return HasMany<TramiteAsignacion, $this> */
    public function tramitesAsignadosComoRevisor(): HasMany
    {
        return $this->hasMany(TramiteAsignacion::class, 'revisor_id');
    }

    /** @return HasOne<PerfilEstudiante, $this> */
    public function perfilEstudiante(): HasOne
    {
        return $this->hasOne(PerfilEstudiante::class);
    }

    /** @return HasOne<PerfilDocente, $this> */
    public function perfilDocente(): HasOne
    {
        return $this->hasOne(PerfilDocente::class);
    }
}
