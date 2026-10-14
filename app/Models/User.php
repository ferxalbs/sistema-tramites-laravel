<?php

namespace App\Models;

use App\Services\MailReadiness;
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
use Illuminate\Support\Facades\Log;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Throwable;

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
 * @property string|null $firma_perfil_ruta
 * @property string|null $firma_perfil_sha256
 * @property Carbon|null $firma_perfil_actualizada_en
 */
#[Fillable(['name', 'email', 'password', 'rol', 'activo', 'estado_cuenta', 'nombres', 'apellidos', 'dni', 'celular', 'cargo_institucional_id', 'correo_alternativo', 'debe_cambiar_password', 'cuenta_provisional'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'firma_perfil_ruta', 'firma_perfil_sha256'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    private bool $verificationMailSubmitted = false;

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
            'firma_perfil_actualizada_en' => 'datetime',
        ];
    }

    /** @return HasMany<Tramite, $this> */
    public function tramitesPropios(): HasMany
    {
        return $this->hasMany(Tramite::class, 'propietario_id');
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->verificationMailSubmitted = false;
        if (! app(MailReadiness::class)->canSend()) {
            return;
        }
        $this->increment('verification_version');
        $this->refresh();
        try {
            $this->notify(new VerifyEmail);
            $this->verificationMailSubmitted = true;
        } catch (Throwable $exception) {
            Log::warning('Verification mail could not be submitted.', ['exception' => $exception::class]);
        }
    }

    public function verificationMailWasSubmitted(): bool
    {
        return $this->verificationMailSubmitted;
    }

    public function scannedSignaturePath(): string
    {
        return $this->firma_perfil_ruta ?? 'firmas-perfil/'.$this->id.'.jpg';
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
