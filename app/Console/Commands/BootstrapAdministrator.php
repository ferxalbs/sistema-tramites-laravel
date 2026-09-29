<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Throwable;

#[Signature('accounts:bootstrap-admin {--nombres=} {--apellidos=} {--email=}')]
#[Description('Crea la primera cuenta administradora de una instalación vacía.')]
class BootstrapAdministrator extends Command
{
    public function handle(): int
    {
        $input = [
            'nombres' => trim((string) $this->option('nombres')),
            'apellidos' => trim((string) $this->option('apellidos')),
            'email' => mb_strtolower(trim((string) $this->option('email'))),
        ];
        $validator = Validator::make($input, [
            'nombres' => ['required', 'string', 'min:2', 'max:120', 'regex:/^[\p{L}\p{M} .\'-]+$/u'],
            'apellidos' => ['required', 'string', 'min:2', 'max:120', 'regex:/^[\p{L}\p{M} .\'-]+$/u'],
            'email' => ['required', 'email', 'max:190', 'regex:/@seoane\.edu\.pe\z/D'],
        ]);

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        if (DB::table('users')->exists()) {
            $this->error('La instalación ya tiene usuarios. El alta inicial está cerrada.');

            return self::FAILURE;
        }

        $password = 'A!9'.bin2hex(random_bytes(18)).'zZ';
        $passwordHash = Hash::make($password);
        $now = now()->toDateTimeString();

        try {
            DB::affectingStatement(
                'INSERT INTO users (name, nombres, apellidos, email, password, rol, activo, estado_cuenta, email_verified_at, debe_cambiar_password, cuenta_provisional, created_at, updated_at) '
                .'SELECT ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ? WHERE NOT EXISTS (SELECT 1 FROM users)',
                [
                    $input['nombres'].' '.$input['apellidos'],
                    $input['nombres'],
                    $input['apellidos'],
                    $input['email'],
                    $passwordHash,
                    'administrador',
                    1,
                    'activo',
                    $now,
                    1,
                    0,
                    $now,
                    $now,
                ],
            );
        } catch (Throwable) {
            // Una respuesta perdida puede corresponder a una inserción confirmada remotamente.
        }

        $user = DB::table('users')->where('email', $input['email'])
            ->first(['password', 'rol', 'activo', 'estado_cuenta', 'debe_cambiar_password']);

        if ($user === null || ! Hash::check($password, $user->password)
            || $user->rol !== 'administrador' || ! $user->activo
            || $user->estado_cuenta !== 'activo' || ! $user->debe_cambiar_password) {
            $this->error('No se pudo confirmar el alta inicial. Revise la cuenta en la base antes de intentar otra operación.');

            return self::FAILURE;
        }

        $this->info('Cuenta administradora inicial confirmada.');
        $this->line('Correo: '.$input['email']);
        $this->line('Contraseña temporal: '.$password);
        $this->warn('Cambie la contraseña al iniciar sesión. Esta contraseña se muestra una sola vez.');

        return self::SUCCESS;
    }
}
