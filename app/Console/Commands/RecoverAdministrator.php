<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RecoverAdministrator extends Command
{
    protected $signature = 'accounts:recover-admin {email} {--confirm-email=} {--output=} {--production : Autoriza expresamente la operación fuera del entorno local}';

    protected $description = 'Recupera una cuenta administradora existente con contraseña temporal y revoca sus sesiones.';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        if ($email !== mb_strtolower(trim((string) $this->option('confirm-email')))
            || (! app()->environment('local', 'testing') && ! $this->option('production'))) {
            $this->error('Confirme el correo exacto con --confirm-email. En producción también se requiere --production.');

            return self::FAILURE;
        }
        $user = User::query()->where('email', $email)->where('rol', 'administrador')
            ->where('activo', true)->where('estado_cuenta', 'activo')->first();
        if ($user === null) {
            $this->error('No existe una cuenta administradora activa con ese correo.');

            return self::FAILURE;
        }
        $output = $this->option('output');
        if (is_string($output)) {
            $directory = realpath(dirname($output));
            $root = strtolower(rtrim(str_replace('\\', '/', base_path()), '/')).'/';
            $resolved = $directory === false ? '' : $directory.DIRECTORY_SEPARATOR.basename($output);
            if ($directory === false || file_exists($resolved)
                || str_starts_with(strtolower(str_replace('\\', '/', $resolved)), $root)) {
                $this->error('El archivo debe ser nuevo y estar fuera del proyecto, en una carpeta privada existente.');

                return self::FAILURE;
            }
            $output = $resolved;
        }
        $password = 'A!9'.bin2hex(random_bytes(12)).'zZ';
        $handle = is_string($output) ? fopen($output, 'x') : false;
        if (is_string($output) && $handle === false) {
            $this->error('No se pudo crear el archivo privado de acceso.');

            return self::FAILURE;
        }
        $changed = false;
        try {
            $access = "Acceso temporal al sistema\nCorreo: {$email}\nContraseña temporal: {$password}\nCambie esta contraseña al iniciar sesión.\n";
            if (is_resource($handle)) {
                if (PHP_OS_FAMILY !== 'Windows') {
                    chmod((string) $output, 0600);
                }
                if (fwrite($handle, $access) !== strlen($access) || ! fflush($handle)) {
                    throw new \RuntimeException('No se pudo escribir el acceso temporal; la contraseña anterior permanece vigente.');
                }
            }
            DB::transaction(function () use ($user, $password): void {
                $user->forceFill(['password' => Hash::make($password), 'debe_cambiar_password' => true, 'remember_token' => null])->save();
                $user->increment('sesion_version');
                DB::table('sessions')->where('user_id', $user->id)->delete();
                DB::table('password_reset_tokens')->where('email', $user->email)->delete();
                DB::table('user_account_events')->insert([
                    'user_id' => $user->id, 'actor_id' => $user->id, 'accion' => 'recovery_cli',
                    'estado_anterior' => $user->estado_cuenta, 'estado_nuevo' => $user->estado_cuenta,
                    'motivo' => 'Recuperación de acceso autorizada desde la consola del servidor.', 'created_at' => now(),
                ]);
            });
            $changed = true;
            if (is_resource($handle)) {
                $this->info('Acceso temporal guardado en el archivo privado indicado.');
            } else {
                $this->line($access);
            }
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
                if (! $changed && is_string($output) && is_file($output)) {
                    unlink($output);
                }
            }
        }

        return self::SUCCESS;
    }
}
