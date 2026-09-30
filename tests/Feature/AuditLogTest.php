<?php

use App\Models\User;

test('the administrative audit screen is no longer exposed', function () {
    $administrator = User::factory()->create(['rol' => 'administrador']);

    $this->actingAs($administrator)->get('/admin/auditoria')->assertNotFound();
});
