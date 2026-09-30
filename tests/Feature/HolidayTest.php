<?php

use App\Models\User;

test('holiday and deadline administration pages are no longer exposed', function () {
    $administrator = User::factory()->create(['rol' => 'administrador']);

    $this->actingAs($administrator)->get('/admin/feriados')->assertNotFound();
    $this->get('/admin/plazos')->assertNotFound();
});
