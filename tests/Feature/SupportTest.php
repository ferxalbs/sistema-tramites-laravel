<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('public help page presents the approved topics without an unconfigured contact channel', function () {
    $this->get(route('support.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ayuda')
            ->has('assistant_topics', 9)
            ->has('faq', 14)
            ->has('tutorials', 6)
            ->where('role_guide', [])
            ->where('whatsapp_url', null)
            ->missing('contact.whatsapp'));
});

test('help uses the configured WhatsApp number and a generic message for each role', function () {
    config(['support.contact.whatsapp' => '+51 (987) 654-321']);

    $this->get(route('support.index', ['dni' => '12345678']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('whatsapp_url', 'https://wa.me/51987654321?text='.rawurlencode(config('support.messages.public')))
            ->where('contact.whatsapp', '+51987654321')
            ->missing('role_guide.0'));

    $student = User::factory()->create(['rol' => 'estudiante']);
    $this->actingAs($student)
        ->get(route('support.index', ['codigo' => 'TRM-2026-000001']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('role_guide', 4)
            ->where('whatsapp_url', 'https://wa.me/51987654321?text='.rawurlencode(config('support.messages.student')))
            ->where('assistant_topics.corregir-observacion.answer', config('support.topics.corregir-observacion.answer')));
});

test('invalid WhatsApp configuration never creates a contact link', function () {
    config(['support.contact.whatsapp' => '000-000-000']);

    $this->get(route('support.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('whatsapp_url', null)
            ->missing('contact.whatsapp'));
});
