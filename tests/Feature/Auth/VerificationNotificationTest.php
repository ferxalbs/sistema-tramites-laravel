<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::emailVerification());
});

test('sends verification notification', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->post(route('verification.send'))
        ->assertRedirect(route('home'));

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('does not send verification notification if email is verified', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('verification.send'))
        ->assertRedirect(route('dashboard', absolute: false));

    Notification::assertNothingSent();
});

test('public resend has a neutral response and limits duplicate notifications', function () {
    Notification::fake();
    $pending = User::factory()->unverified()->create([
        'email' => 'a.reenvio@seoane.edu.pe',
        'activo' => false,
        'estado_cuenta' => 'pendiente',
    ]);

    $first = $this->post(route('registration.resend'), ['email' => $pending->email]);
    $unknown = $this->post(route('registration.resend'), ['email' => 'a.desconocida@seoane.edu.pe']);
    $again = $this->post(route('registration.resend'), ['email' => $pending->email]);

    $first->assertSessionHas('status');
    $unknown->assertSessionHas('status');
    $again->assertSessionHas('status');
    Notification::assertSentTo($pending, VerifyEmail::class, 1);
});

test('resending verification invalidates the previously signed public link', function () {
    Notification::fake();
    $pending = User::factory()->unverified()->create([
        'email' => 'a.enlaces@seoane.edu.pe',
        'activo' => false,
        'estado_cuenta' => 'pendiente',
    ]);

    $pending->sendEmailVerificationNotification();
    $oldVersion = $pending->fresh()->verification_version;
    $oldUrl = Notification::sent($pending, VerifyEmail::class)->last()->toMail($pending->fresh())->actionUrl;

    $this->post(route('registration.resend'), ['email' => $pending->email])->assertSessionHas('status');
    $newVersion = $pending->fresh()->verification_version;
    $newUrl = Notification::sent($pending, VerifyEmail::class)->last()->toMail($pending->fresh())->actionUrl;

    expect($newVersion)->toBe($oldVersion + 1)
        ->and($oldUrl)->toContain('version='.$oldVersion)
        ->and($newUrl)->toContain('version='.$newVersion);
    $this->get($oldUrl)->assertForbidden();
    expect($pending->fresh()->hasVerifiedEmail())->toBeFalse();
    $this->get($newUrl)->assertRedirect(route('login'));
    expect($pending->fresh()->hasVerifiedEmail())->toBeTrue();
    Notification::assertSentTo($pending, VerifyEmail::class, 2);
});
