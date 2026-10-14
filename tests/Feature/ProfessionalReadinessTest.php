<?php

use App\Models\Tramite;
use App\Models\TramiteDocumentoFinal;
use App\Models\TramitePlantilla;
use App\Models\User;
use App\Services\MailReadiness;
use App\Services\PdfDocumentGenerator;
use App\Services\Tramites\TramiteTemplateEligibility;
use Database\Seeders\TramitePlantillaSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    config(['filesystems.disks.local.root' => Storage::disk('local')->path('')]);
});

test('administrator recovery revokes sessions and leaves other accounts unchanged', function () {
    $admin = User::factory()->create(['rol' => 'administrador', 'remember_token' => 'old-token']);
    $other = User::factory()->create(['rol' => 'docente']);
    $oldOtherPassword = $other->password;
    DB::table('sessions')->insert(['id' => 'test-session', 'user_id' => $admin->id, 'payload' => '', 'last_activity' => time()]);
    DB::table('password_reset_tokens')->insert(['email' => $admin->email, 'token' => 'previous-token', 'created_at' => now()]);
    $output = sys_get_temp_dir().'/tramites-recovery-test-'.Str::uuid().'.txt';
    try {
        $this->artisan('accounts:recover-admin', ['email' => $admin->email, '--confirm-email' => $admin->email, '--output' => $output])->assertSuccessful();
        $access = file_get_contents($output);
        preg_match('/Contraseña temporal: (.+)/u', $access, $match);
        expect(Hash::check($match[1], $admin->fresh()->password))->toBeTrue()
            ->and($admin->fresh()->sesion_version)->toBe(1)
            ->and($admin->fresh()->debe_cambiar_password)->toBeTrue()
            ->and($admin->fresh()->remember_token)->toBeNull()
            ->and(DB::table('sessions')->count())->toBe(0)
            ->and(DB::table('password_reset_tokens')->count())->toBe(0)
            ->and($other->fresh()->password)->toBe($oldOtherPassword);
    } finally {
        if (is_file($output)) {
            unlink($output);
        }
    }
});

test('administrator recovery requires exact confirmation and never overwrites an existing private file', function () {
    $admin = User::factory()->create(['rol' => 'administrador']);
    $oldPassword = $admin->password;
    $output = sys_get_temp_dir().'/tramites-existing-test-'.Str::uuid().'.txt';
    file_put_contents($output, 'keep existing file');
    try {
        $this->artisan('accounts:recover-admin', ['email' => $admin->email, '--confirm-email' => 'wrong@seoane.edu.pe', '--output' => $output])->assertFailed();
        $this->artisan('accounts:recover-admin', ['email' => $admin->email, '--confirm-email' => $admin->email, '--output' => $output])->assertFailed();
        expect($admin->fresh()->password)->toBe($oldPassword)->and(file_get_contents($output))->toBe('keep existing file');
    } finally {
        unlink($output);
    }
});

test('readiness checks database and private storage without exposing internals', function () {
    $response = $this->getJson(route('system.ready'))->assertOk()->assertExactJson(['status' => 'ok']);
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    config(['filesystems.disks.local.root' => sys_get_temp_dir().'/missing-'.Str::uuid()]);
    $this->getJson(route('system.ready'))->assertStatus(503)->assertExactJson(['status' => 'unavailable']);
});

test('page scripts use a unique CSP nonce and browser security headers', function () {
    $first = $this->get(route('login'))->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    preg_match("/nonce-([^']+)/", $first->headers->get('Content-Security-Policy'), $match);
    expect($match[1] ?? '')->not->toBe('');
    $first->assertSee('nonce="'.$match[1].'"', false);
    $second = $this->get(route('login'))->assertOk();
    expect($second->headers->get('Content-Security-Policy'))->not->toBe($first->headers->get('Content-Security-Policy'));
});

test('unconfigured and log mailers never acknowledge delivery and recovery stays neutral', function () {
    Notification::fake();
    $user = User::factory()->create(['rol' => 'docente', 'email' => 'docente.sinmail@seoane.edu.pe']);
    foreach (['array', 'log', 'failover'] as $mailer) {
        config(['mail.default' => $mailer, 'mail.test_inbox' => false]);
        expect(app(MailReadiness::class)->canSend())->toBeFalse();
    }
    $this->post(route('password.email'), ['email' => $user->email])->assertSessionHasNoErrors();
    Notification::assertNothingSent();
    expect(DB::table('password_reset_tokens')->count())->toBe(0);

    config(['mail.default' => 'smtp', 'mail.test_inbox' => true]);
    expect(app(MailReadiness::class)->canSend())->toBeTrue();
    $this->app['env'] = 'production';
    expect(app(MailReadiness::class)->canSend())->toBeFalse();
});

test('failed verification mail never reports success or verifies the user', function () {
    config(['mail.default' => 'smtp', 'mail.test_inbox' => false]);
    $user = User::factory()->unverified()->create(['rol' => 'docente']);
    Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('Simulated SMTP failure'));
    $this->actingAs($user)->post(route('verification.send'))->assertSessionHasErrors('email');
    expect($user->fresh()->email_verified_at)->toBeNull()
        ->and($user->verificationMailWasSubmitted())->toBeFalse();
});

test('practice period and accredited hours are validated before a draft is stored', function () {
    $this->seed(TramitePlantillaSeeder::class);
    $admin = User::factory()->create(['rol' => 'administrador']);
    $tramite = Tramite::factory()->create(['tipo_documento' => 'CONSTANCIA_PRACTICA', 'estado' => 'digitalizado']);
    $template = TramitePlantilla::query()->where('codigo', 'CONSTANCIA_PRACTICA_REFERENCIAL')->sole();
    $payload = [
        'plantilla_id' => $template->id, 'fecha_documento' => now()->toDateString(), 'lugar' => 'Lima',
        'asunto' => 'Prácticas de prueba', 'contenido_principal' => 'Evaluación de prueba', 'preparar' => false,
        'campos' => ['PERIODO_INICIO' => '2026-10-01', 'PERIODO_FIN' => '2026-09-30', 'HORAS_PRACTICAS' => '120'],
    ];
    $this->actingAs($admin)->post(route('tramites.borradores.store', $tramite), $payload)->assertSessionHasErrors('campos.PERIODO_FIN');
    $payload['campos']['PERIODO_FIN'] = '2026-10-31';
    foreach (['-10', '0', '1e999'] as $hours) {
        $payload['campos']['HORAS_PRACTICAS'] = $hours;
        $this->post(route('tramites.borradores.store', $tramite), $payload)->assertSessionHasErrors('campos.HORAS_PRACTICAS');
    }
    expect($tramite->borradores()->count())->toBe(0);
});

test('profile signature replacement is versioned and preserves its previous bytes', function () {
    $teacher = User::factory()->create(['rol' => 'docente']);
    $this->actingAs($teacher)->post(route('profile.signature'), [
        'firma' => UploadedFile::fake()->image('firma.png', 220, 80), 'confirmar_uso' => true,
    ])->assertSessionHasNoErrors();
    $first = $teacher->fresh()->scannedSignaturePath();
    $original = Storage::disk('local')->get($first);
    $this->post(route('profile.signature'), [
        'firma' => UploadedFile::fake()->image('firma-nueva.png', 240, 90), 'confirmar_uso' => true,
    ])->assertSessionHasNoErrors();
    $teacher->refresh();
    expect($teacher->scannedSignaturePath())->not->toBe($first)
        ->and(Storage::disk('local')->get($first))->toBe($original)
        ->and($teacher->firma_perfil_sha256)->toBe(hash('sha256', Storage::disk('local')->get($teacher->scannedSignaturePath())))
        ->and(DB::table('user_account_events')->where('accion', 'update_signature')->count())->toBe(2);
    $this->post(route('profile.signature'), [
        'firma' => UploadedFile::fake()->image('demasiado-ancha.png', 4100, 30), 'confirmar_uso' => true,
    ])->assertSessionHasErrors('firma');
    expect($teacher->fresh()->scannedSignaturePath())->toBe($teacher->scannedSignaturePath());
});

test('signature metadata failure preserves the published signature and removes only the new file', function () {
    $teacher = User::factory()->create(['rol' => 'docente']);
    $path = 'firmas-perfil/'.$teacher->id.'/previous.jpg';
    Storage::disk('local')->put($path, 'original unchanged bytes');
    $teacher->forceFill(['firma_perfil_ruta' => $path])->save();
    DB::listen(function (QueryExecuted $query): void {
        if (str_contains($query->sql, 'insert into "user_account_events"')) {
            throw new RuntimeException('Simulated audit storage failure');
        }
    });
    $this->withoutExceptionHandling();
    expect(fn () => $this->actingAs($teacher)->post(route('profile.signature'), [
        'firma' => UploadedFile::fake()->image('new.png', 200, 60), 'confirmar_uso' => true,
    ]))->toThrow(RuntimeException::class, 'Simulated audit storage failure');
    expect($teacher->fresh()->scannedSignaturePath())->toBe($path)
        ->and(Storage::disk('local')->get($path))->toBe('original unchanged bytes')
        ->and(Storage::disk('local')->allFiles('firmas-perfil/'.$teacher->id))->toBe([$path]);
});

test('proposed models are typed, require validation for official issuance and survive fresh installation', function () {
    $this->seed(TramitePlantillaSeeder::class);
    $this->seed(TramitePlantillaSeeder::class);
    $admin = User::factory()->create(['rol' => 'administrador']);
    $teacher = User::factory()->create(['rol' => 'docente']);
    $plantilla = TramitePlantilla::query()->where('codigo', 'CONSTANCIA_PRACTICA_PROPUESTO')->sole();
    $tramite = Tramite::factory()->create(['tipo_documento' => 'CONSTANCIA_PRACTICA']);
    $other = Tramite::factory()->create(['tipo_documento' => 'MEMORANDO_SIMPLE']);
    config(['tramites.modelos_prueba' => true]);
    expect(TramiteTemplateEligibility::canIssue($tramite, $plantilla))->toBeTrue()
        ->and(TramiteTemplateEligibility::canIssue($other, $plantilla))->toBeFalse();
    $this->actingAs($admin)->get(route('tramites.borradores.create', $tramite))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('modelo_oficial_pendiente', true)->where('emision_prueba_disponible', true));
    $this->app['env'] = 'production';
    expect(TramiteTemplateEligibility::canIssue($tramite, $plantilla))->toBeFalse();
    $this->app['env'] = 'testing';
    config(['tramites.modelos_prueba' => false]);
    $this->actingAs($teacher)->post(route('admin.templates.validate-model', $plantilla), [
        'fuente_modelo' => 'Acta de validación institucional', 'confirmar_validacion' => true,
    ])->assertForbidden();
    $this->actingAs($admin)->post(route('admin.templates.validate-model', $plantilla), [
        'fuente_modelo' => 'Acta de validación institucional', 'confirmar_validacion' => true,
    ])->assertSessionHasNoErrors();
    expect(TramiteTemplateEligibility::canIssue($tramite, $plantilla->fresh()))->toBeTrue()
        ->and($plantilla->fresh()->validada_por)->toBe($admin->id);
    $this->get(route('tramites.borradores.create', $tramite))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('modelo_oficial_pendiente', false)->where('emision_prueba_disponible', false));
    $this->post(route('admin.templates.version', $plantilla), [
        'nombre' => 'Prácticas versión modificada', 'descripcion' => '',
        'contenido' => '{{NUMERO_DOCUMENTO_PREVIO}} {{CONTENIDO_PRINCIPAL}}', 'publicar' => true,
    ])->assertSessionHasNoErrors();
    $new = TramitePlantilla::query()->where('codigo', $plantilla->codigo)->where('version', 2)->sole();
    expect($new->validada_en)->toBeNull()->and(TramiteTemplateEligibility::canIssue($tramite, $new))->toBeFalse();
});

test('verification identifies emitted test PDFs without exposing content and ignores unfinished ones', function () {
    $document = TramiteDocumentoFinal::factory()->create([
        'estado' => 'emitido', 'activo' => true, 'contenido_snapshot' => ['es_prueba' => true, 'private' => 'hidden'],
        'codigo_verificacion' => 'ABCD-EF12-3456-7890',
    ]);
    $this->get(route('documentos.verificar', ['codigo' => $document->codigo_verificacion]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('resultado.estado', 'prueba')->missing('resultado.documento.contenido_snapshot'));
    $document->update(['estado' => 'generando']);
    $this->get(route('documentos.verificar', ['codigo' => $document->codigo_verificacion]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('resultado.estado', 'invalido')->where('resultado.documento', null));
});

test('legacy and proposed templates can keep their supported variables when versioned', function (string $content) {
    $admin = User::factory()->create(['rol' => 'administrador']);
    $template = TramitePlantilla::factory()->create(['contenido' => $content]);
    $this->actingAs($admin)->post(route('admin.templates.version', $template), [
        'nombre' => 'Modelo revisado', 'descripcion' => '', 'contenido' => $content, 'publicar' => true,
    ])->assertSessionHasNoErrors()->assertRedirect(route('admin.templates.index'));
    expect($template->fresh()->contenido)->toBe($content)
        ->and(TramitePlantilla::query()->where('codigo', $template->codigo)->where('version', 2)->sole()->contenido)->toBe($content);
})->with([
    'legacy body' => ['INFORME {{CONTENIDO}}'],
    'proposed body' => ['CONSTANCIA {{CONTENIDO_PRINCIPAL}}'],
]);

test('changing fields or their order invalidates a previous model validation', function () {
    $this->seed(TramitePlantillaSeeder::class);
    $admin = User::factory()->create(['rol' => 'administrador']);
    $template = TramitePlantilla::query()->where('codigo', 'CONSTANCIA_PRACTICA_PROPUESTO')->sole();
    $field = DB::table('tramite_plantilla_campos')->where('plantilla_id', $template->id)->orderBy('orden')->first();
    $validation = ['fuente_modelo' => 'Autorización de ejemplo para la prueba', 'confirmar_validacion' => true];
    $this->actingAs($admin)->post(route('admin.templates.validate-model', $template), $validation)->assertSessionHasNoErrors();
    $this->patch(route('admin.templates.fields.update', [$template, $field->id]), [
        'etiqueta' => 'Etiqueta revisada', 'grupo' => $field->grupo, 'tipo_campo' => $field->tipo_campo,
        'obligatorio' => (bool) $field->obligatorio, 'requiere_confirmacion' => (bool) $field->requiere_confirmacion,
        'permite_html' => (bool) $field->permite_html, 'longitud_maxima' => $field->longitud_maxima,
        'texto_ayuda' => '', 'activo' => true,
    ])->assertSessionHasNoErrors();
    expect($template->fresh()->validada_en)->toBeNull()->and($template->fresh()->validada_por)->toBeNull();
    $this->post(route('admin.templates.validate-model', $template), $validation)->assertSessionHasNoErrors();
    $this->patch(route('admin.templates.fields.move', [$template, $field->id]), ['direccion' => 'down'])->assertSessionHasNoErrors();
    expect($template->fresh()->validada_en)->toBeNull()->and($template->fresh()->fuente_modelo)->toBeNull();
});

test('both memorandum PDFs retain static approved paragraphs outside generic fields', function () {
    config(['app.url' => 'http://127.0.0.1:8000']);
    foreach (['simple', 'multiple'] as $modalidad) {
        $pdf = app(PdfDocumentGenerator::class)->generate([
            'institucion' => 'Institución de prueba', 'numero' => 'PRUEBA-2026-001',
            'codigo_expediente' => 'PRUEBA-001', 'tipo_documento_salida' => 'memorando', 'modalidad_documento' => $modalidad,
            'contenido_renderizado' => "MEMORANDO\nA: Responsable de prueba\nDe: Docente de prueba\nAsunto: Prueba\n\nCLAUSULA_APROBADA_COMPLETA\n\nANEXO_FINAL_CONSERVADO\n\nDocente de prueba",
            'firmante_nombre' => 'Docente de prueba', 'codigo_verificacion' => '',
            'decision' => 'rechazado', 'conclusion' => 'MOTIVO_RECHAZO_CONSERVADO', 'comentario_publico' => 'COMUNICACION_CONSERVADA',
        ]);
        expect($pdf['bytes'])->toStartWith('%PDF-')->toContain('CLAUSULA_APROBADA_COMPLETA', 'ANEXO_FINAL_CONSERVADO', 'MOTIVO_RECHAZO_CONSERVADO', 'COMUNICACION_CONSERVADA');
    }
});
