<?php

use Illuminate\Support\Facades\Storage;

test('private storage marker detects missing or changed files', function () {
    Storage::fake('local');

    $this->artisan('storage:verify-persistence', ['--check' => 'not-a-marker'])->assertFailed();
    $this->artisan('storage:verify-persistence', ['--write' => true])->assertSuccessful();

    $marker = Storage::disk('local')->get('.persistence-probe');
    $this->artisan('storage:verify-persistence', ['--check' => $marker])->assertSuccessful();

    Storage::disk('local')->delete('.persistence-probe');
    $this->artisan('storage:verify-persistence', ['--check' => $marker])->assertFailed();
});
