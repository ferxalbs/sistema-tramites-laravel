<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::emailVerification());
});

test('email verification screen can be rendered', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get(route('verification.notice'));

    $response->assertOk();
});

test('unverified users are redirected to the email verification prompt', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get(route('appearance.edit'));

    $response->assertRedirect(route('verification.notice'));
});

test('email can be verified', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    Event::assertDispatched(Verified::class);

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
});

test('email is not verified with invalid hash', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('wrong-email')],
    );

    $this->actingAs($user)->get($verificationUrl);

    Event::assertNotDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('email is not verified with invalid user id', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => 123, 'hash' => sha1($user->email)],
    );

    $this->actingAs($user)->get($verificationUrl);

    Event::assertNotDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('verified user is redirected to dashboard from verification prompt', function () {
    $user = User::factory()->create();

    Event::fake();

    $response = $this->actingAs($user)->get(route('verification.notice'));

    Event::assertNotDispatched(Verified::class);
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('already verified user visiting verification link is redirected without firing event again', function () {
    $user = User::factory()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->actingAs($user)->get($verificationUrl)
        ->assertRedirect(route('dashboard', absolute: false).'?verified=1');

    Event::assertNotDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('signed public verification keeps a registered student pending until administrative approval', function () {
    $student = User::factory()->unverified()->create([
        'email' => 'a.verificada@seoane.edu.pe',
        'activo' => false,
        'estado_cuenta' => 'pendiente',
    ]);
    $administrator = User::factory()->create(['rol' => 'administrador']);
    Event::fake([Verified::class]);
    $url = URL::temporarySignedRoute('registration.verify', now()->addHours(24), [
        'id' => $student->id,
        'hash' => sha1($student->email),
    ]);

    $this->get($url)->assertRedirect(route('login'));
    $this->assertGuest();
    expect($student->fresh()->hasVerifiedEmail())->toBeTrue()
        ->and($student->fresh()->activo)->toBeFalse();
    Event::assertDispatchedTimes(Verified::class, 1);
    $this->get($url)->assertGone();

    $this->actingAs($administrator)->patch(route('admin.users.update', $student), ['accion' => 'activate'])
        ->assertRedirect();
    $this->post(route('logout'));
    $this->post(route('login.store'), ['email' => $student->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($student);
});

test('public verification rejects altered or expired signed links', function () {
    $student = User::factory()->unverified()->create(['activo' => false, 'estado_cuenta' => 'pendiente']);
    Event::fake([Verified::class]);

    $wrongHash = URL::temporarySignedRoute('registration.verify', now()->addHour(), [
        'id' => $student->id, 'hash' => sha1('wrong@example.com'),
    ]);
    $expired = URL::temporarySignedRoute('registration.verify', now()->subMinute(), [
        'id' => $student->id, 'hash' => sha1($student->email),
    ]);

    $this->get($wrongHash)->assertForbidden();
    $this->get($expired)->assertForbidden();
    expect($student->fresh()->hasVerifiedEmail())->toBeFalse();
    Event::assertNotDispatched(Verified::class);
});
