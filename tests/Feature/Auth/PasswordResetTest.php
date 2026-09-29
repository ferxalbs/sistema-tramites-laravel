<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::resetPasswords());
});

test('reset password link screen can be rendered', function () {
    $response = $this->get(route('password.request'));

    $response->assertOk();
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'docente.reset@seoane.edu.pe', 'rol' => 'docente']);

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'docente.reset@seoane.edu.pe', 'rol' => 'docente']);

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = $this->get(route('password.reset', $notification->token));

        $response->assertOk();

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'docente.reset@seoane.edu.pe', 'rol' => 'docente']);

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'NuevaClave2026',
            'password_confirmation' => 'NuevaClave2026',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        expect($user->fresh()->sesion_version)->toBe(1);
        expect(Hash::check('NuevaClave2026', $user->fresh()->password))->toBeTrue();

        $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'OtraClave2026',
            'password_confirmation' => 'OtraClave2026',
        ])->assertSessionHasErrors('email');

        return true;
    });
});

test('password cannot be reset with invalid token', function () {
    $user = User::factory()->create();

    $response = $this->post(route('password.update'), [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertSessionHasErrors('email');
});

test('recovery response is neutral for unknown and inactive accounts', function () {
    Notification::fake();
    $inactive = User::factory()->create([
        'email' => 'docente.inactivo@seoane.edu.pe',
        'rol' => 'docente',
        'activo' => false,
        'estado_cuenta' => 'inactivo',
    ]);

    foreach (['docente.desconocido@seoane.edu.pe', $inactive->email] as $email) {
        $this->post(route('password.email'), ['email' => $email])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', trans('passwords.sent'));
    }

    Notification::assertNothingSent();
    $this->assertDatabaseCount('password_reset_tokens', 0);
});

test('a newer link invalidates the earlier link and the expiry is thirty minutes', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'docente.tokens@seoane.edu.pe', 'rol' => 'docente']);

    $this->post(route('password.email'), ['email' => $user->email]);
    Notification::assertSentTo($user, ResetPassword::class);
    $first = Notification::sent($user, ResetPassword::class)->first()->token;

    $this->post(route('password.email'), ['email' => $user->email]);
    $second = Notification::sent($user, ResetPassword::class)->last()->token;
    expect($first)->not->toBe($second);

    $this->post(route('password.update'), [
        'token' => $first,
        'email' => $user->email,
        'password' => 'NuevaClave2026',
        'password_confirmation' => 'NuevaClave2026',
    ])->assertSessionHasErrors('email');

    $this->travel(31)->minutes();
    $this->post(route('password.update'), [
        'token' => $second,
        'email' => $user->email,
        'password' => 'NuevaClave2026',
        'password_confirmation' => 'NuevaClave2026',
    ])->assertSessionHasErrors('email');
});

test('recovery is limited by email and by IP while keeping the same public response', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'docente.limite@seoane.edu.pe', 'rol' => 'docente']);

    for ($attempt = 0; $attempt < 4; $attempt++) {
        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', trans('passwords.sent'));
    }

    Notification::assertSentTo($user, ResetPassword::class, 3);
    expect(RateLimiter::attempts('password-recovery:email:'.hash('sha256', $user->email)))->toBe(3);
    expect(RateLimiter::attempts('password-recovery:ip:'.hash('sha256', '127.0.0.1')))->toBe(3);
});

test('recovery IP limit also applies across different addresses', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'docente.ip@seoane.edu.pe', 'rol' => 'docente']);

    for ($attempt = 0; $attempt < 10; $attempt++) {
        $this->post(route('password.email'), ['email' => "desconocido{$attempt}@seoane.edu.pe"])
            ->assertSessionHasNoErrors();
    }

    $this->post(route('password.email'), ['email' => $user->email])
        ->assertSessionHas('status', trans('passwords.sent'));
    Notification::assertNothingSent();
    expect(RateLimiter::attempts('password-recovery:ip:'.hash('sha256', '127.0.0.1')))->toBe(10);
});

test('reset link uses configured application URL instead of request host', function () {
    Notification::fake();
    config()->set('app.url', 'https://tramites.seoane.edu.pe');
    $user = User::factory()->create(['email' => 'docente.url@seoane.edu.pe', 'rol' => 'docente']);

    $this->withServerVariables(['HTTP_HOST' => 'attacker.invalid'])
        ->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
        $url = $notification->toMail($user)->actionUrl;

        return str_starts_with($url, 'https://tramites.seoane.edu.pe/')
            && ! str_contains($url, 'attacker.invalid');
    });
});

test('an account deactivated after link issuance cannot reset its password', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'docente.revocado@seoane.edu.pe', 'rol' => 'docente']);
    $originalPassword = $user->password;

    $this->post(route('password.email'), ['email' => $user->email]);
    Notification::assertSentTo($user, ResetPassword::class);
    $token = Notification::sent($user, ResetPassword::class)->first()->token;
    $user->update(['activo' => false, 'estado_cuenta' => 'inactivo']);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'NuevaClave2026',
        'password_confirmation' => 'NuevaClave2026',
    ])->assertSessionHasErrors('email');

    expect($user->fresh()->password)->toBe($originalPassword);
});

test('password reset revokes an existing session version', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'docente.sesion@seoane.edu.pe', 'rol' => 'docente']);
    $this->post(route('password.email'), ['email' => $user->email]);
    $token = Notification::sent($user, ResetPassword::class)->first()->token;

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'NuevaClave2026',
        'password_confirmation' => 'NuevaClave2026',
    ])->assertSessionHasNoErrors();

    $this->actingAs($user)->withSession(['account_session_version' => 0])
        ->get(route('dashboard'))->assertForbidden();
    $this->assertGuest();
});

test('password reset removes database sessions for that account', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'docente.db-sesion@seoane.edu.pe', 'rol' => 'docente']);
    $otherUser = User::factory()->create();
    $this->post(route('password.email'), ['email' => $user->email]);
    $token = Notification::sent($user, ResetPassword::class)->first()->token;

    foreach ([$user, $otherUser] as $sessionUser) {
        DB::table('sessions')->insert([
            'id' => 'test-session-'.$sessionUser->id,
            'user_id' => $sessionUser->id,
            'payload' => '',
            'last_activity' => time(),
        ]);
    }

    config()->set('session.driver', 'database');
    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'NuevaClave2026',
        'password_confirmation' => 'NuevaClave2026',
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseMissing('sessions', ['id' => 'test-session-'.$user->id]);
    $this->assertDatabaseHas('sessions', ['id' => 'test-session-'.$otherUser->id]);
});
