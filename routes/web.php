<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\RegistrationVerificationController;
use App\Http\Controllers\TramiteAsignacionController;
use App\Http\Controllers\TramiteBorradorController;
use App\Http\Controllers\TramiteController;
use App\Http\Controllers\TramiteDocumentoFinalController;
use App\Http\Controllers\TramiteEntregaAdminController;
use App\Http\Controllers\TramiteEntregaController;
use App\Http\Controllers\TramiteEstudianteController;
use App\Http\Controllers\TramiteReportController;
use App\Http\Controllers\TramiteRevisionController;
use App\Http\Controllers\TramiteVerificacionPublicaController;
use App\Http\Controllers\UserAccountController;
use App\Services\SupportKnowledgeBase;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

Route::inertia('/', 'welcome')->name('home');
Route::get('ayuda', fn (Request $request, SupportKnowledgeBase $support): InertiaResponse => Inertia::render('ayuda', $support->forRequest($request)))
    ->name('support.index');
Route::get('verificar-documento', [TramiteVerificacionPublicaController::class, 'show'])
    ->middleware('throttle:30,1')
    ->withoutMiddleware([
        PreventRequestForgery::class,
        StartSession::class,
        ShareErrorsFromSession::class,
    ])
    ->name('documentos.verificar');
Route::get('registro/verificar/{id}/{hash}', RegistrationVerificationController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->whereNumber('id')
    ->name('registration.verify');
Route::post('registro/reenviar', [RegistrationVerificationController::class, 'resend'])
    ->middleware('throttle:6,1')
    ->name('registration.resend');
Route::get('cambiar-contrasena', fn (): InertiaResponse => Inertia::render('auth/change-required-password', [
    'passwordRules' => Password::defaults()->toPasswordRulesString(),
]))->middleware('auth')->name('password.change-required');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)
        ->middleware('role:estudiante,asistente,docente,administrador')
        ->name('dashboard');

    Route::get('buscar', GlobalSearchController::class)
        ->middleware('role:asistente,docente,administrador')
        ->name('search.index');

    Route::middleware('role:asistente,administrador')->group(function (): void {
        Route::get('admin/reportes', [TramiteReportController::class, 'index'])->name('admin.reports.index');
        Route::resource('tramites', TramiteController::class)->only(['index', 'create', 'store', 'show']);
        Route::get('tramites/{tramite}/comprobante', [TramiteController::class, 'receipt'])
            ->name('tramites.receipt');
        Route::get('tramites/{tramite}/borradores/crear', [TramiteBorradorController::class, 'create'])
            ->name('tramites.borradores.create');
        Route::post('tramites/{tramite}/borradores', [TramiteBorradorController::class, 'store'])
            ->name('tramites.borradores.store');
        Route::post('tramites/{tramite}/preparar-asignacion', [TramiteBorradorController::class, 'prepareAssignment'])
            ->name('tramites.asignacion.prepare');
    });

    Route::middleware('role:asistente')->group(function (): void {
        Route::get('tramites/{tramite}/editar', [TramiteController::class, 'edit'])->name('tramites.edit');
        Route::put('tramites/{tramite}', [TramiteController::class, 'update'])->name('tramites.update');
        Route::post('tramites/{tramite}/documentos', [TramiteController::class, 'upload'])
            ->name('tramites.documentos.store');
        Route::post('tramites/{tramite}/documentos/{documento}/reemplazar', [TramiteController::class, 'replace'])
            ->name('tramites.documentos.replace');
        Route::post('tramites/{tramite}/subsanaciones', [TramiteController::class, 'correct'])
            ->name('tramites.subsanaciones.store');
        Route::get('asignaciones', [TramiteAsignacionController::class, 'index'])->name('tramites.asignaciones.index');
        Route::get('tramites/{tramite}/asignar', [TramiteAsignacionController::class, 'create'])->name('tramites.asignaciones.create');
        Route::post('tramites/{tramite}/asignar', [TramiteAsignacionController::class, 'store'])->name('tramites.asignaciones.store');
        Route::get('tramites/{tramite}/reasignar', [TramiteAsignacionController::class, 'reassign'])->name('tramites.asignaciones.reassign');
        Route::post('tramites/{tramite}/reasignar', [TramiteAsignacionController::class, 'update'])->name('tramites.asignaciones.update');
        Route::post('tramites/{tramite}/cancelar-asignacion', [TramiteAsignacionController::class, 'cancel'])->name('tramites.asignaciones.cancel');
        Route::get('tramites/{tramite}/revision/corregir', [TramiteBorradorController::class, 'correction'])
            ->name('tramites.revision.correction');
        Route::post('tramites/{tramite}/revision/corregir', [TramiteBorradorController::class, 'correct'])
            ->name('tramites.revision.correct');
        Route::get('tramites/{tramite}/documento-final', [TramiteDocumentoFinalController::class, 'preview'])
            ->name('tramites.documento-final.preview');
        Route::post('tramites/{tramite}/documento-final', [TramiteDocumentoFinalController::class, 'emit'])
            ->name('tramites.documento-final.emit');
    });

    Route::middleware('role:docente')->group(function (): void {
        Route::get('mis-asignaciones', [TramiteAsignacionController::class, 'docenteIndex'])->name('asignaciones.docente.index');
        Route::get('mis-asignaciones/{tramite}', [TramiteAsignacionController::class, 'docenteShow'])->name('asignaciones.docente.show');
    });

    Route::middleware('role:administrador')->group(function (): void {
        Route::get('admin/reportes/exportar', [TramiteReportController::class, 'export'])->name('admin.reports.export');
        Route::get('admin/entregas', [TramiteEntregaAdminController::class, 'index'])->name('admin.deliveries.index');
        Route::patch('admin/entregas/medios/{medio}', [TramiteEntregaAdminController::class, 'updateMedium'])->name('admin.deliveries.media.update');
        Route::patch('admin/entregas/plantillas/{plantilla}', [TramiteEntregaAdminController::class, 'updateTemplate'])->name('admin.deliveries.templates.update');
        Route::post('tramites/{tramite}/entrega/{entrega}/anular', [TramiteEntregaController::class, 'annul'])
            ->name('tramites.entrega.anular');
        Route::post('tramites/{tramite}/entrega/reabrir', [TramiteEntregaController::class, 'reopen'])
            ->name('tramites.entrega.reabrir');
        Route::get('admin/feriados', [HolidayController::class, 'index'])->name('admin.holidays.index');
        Route::post('admin/feriados', [HolidayController::class, 'store'])->name('admin.holidays.store');
        Route::patch('admin/feriados/{holiday}/desactivar', [HolidayController::class, 'deactivate'])->whereNumber('holiday')->name('admin.holidays.deactivate');
        Route::get('admin/auditoria', AuditLogController::class)->name('admin.audit.index');
        Route::get('admin/usuarios', [UserAccountController::class, 'index'])->name('admin.users.index');
        Route::get('admin/usuarios/crear', [UserAccountController::class, 'create'])->name('admin.users.create');
        Route::post('admin/usuarios', [UserAccountController::class, 'store'])->name('admin.users.store');
        Route::get('admin/usuarios/{user}/editar', [UserAccountController::class, 'edit'])->name('admin.users.edit');
        Route::put('admin/usuarios/{user}', [UserAccountController::class, 'save'])->name('admin.users.save');
        Route::patch('admin/usuarios/{user}/estado', [UserAccountController::class, 'update'])->name('admin.users.update');
        Route::post('admin/usuarios/{user}/restablecer-contrasena', [UserAccountController::class, 'resetPassword'])->name('admin.users.reset-password');
        Route::get('revisiones-oficina', [TramiteAsignacionController::class, 'oficinaIndex'])->name('asignaciones.oficina.index');
        Route::get('revisiones-oficina/{tramite}', [TramiteAsignacionController::class, 'oficinaShow'])->name('asignaciones.oficina.show');
    });

    Route::middleware('role:docente,administrador')->group(function (): void {
        Route::post('tramites/{tramite}/revision/iniciar', [TramiteRevisionController::class, 'start'])
            ->name('tramites.revision.start');
        Route::post('tramites/{tramite}/revision/observar', [TramiteRevisionController::class, 'observe'])
            ->name('tramites.revision.observe');
        Route::post('tramites/{tramite}/revision/decidir', [TramiteRevisionController::class, 'decide'])
            ->name('tramites.revision.decide');
    });

    Route::middleware('role:estudiante')->group(function (): void {
        Route::get('mis-tramites', [TramiteEstudianteController::class, 'index'])->name('estudiante.tramites.index');
        Route::get('mis-tramites/{tramite}', [TramiteEstudianteController::class, 'show'])->name('estudiante.tramites.show');
    });

    Route::middleware('role:asistente,administrador')->group(function (): void {
        Route::get('tramites/{tramite}/entrega', [TramiteEntregaController::class, 'show'])->name('tramites.entrega.show');
        Route::post('tramites/{tramite}/entrega/preparar', [TramiteEntregaController::class, 'prepare'])->name('tramites.entrega.prepare');
        Route::post('tramites/{tramite}/entrega/firma', [TramiteEntregaController::class, 'registerSignature'])->name('tramites.entrega.firma');
        Route::post('tramites/{tramite}/entrega/registrar', [TramiteEntregaController::class, 'registerDelivery'])->name('tramites.entrega.registrar');
        Route::post('tramites/{tramite}/entrega/cerrar', [TramiteEntregaController::class, 'close'])->name('tramites.entrega.cerrar');
    });

    Route::post('tramites/{tramite}/entrega/confirmar', [TramiteEntregaController::class, 'confirm'])
        ->middleware('role:asistente,administrador,estudiante')
        ->name('tramites.entrega.confirmar');

    Route::get('tramites/{tramite}/entrega/firmas/{firma}/descargar', [TramiteEntregaController::class, 'downloadSignature'])
        ->name('tramites.firmas.descargar');
    Route::get('tramites/{tramite}/entrega/evidencias/{evidencia}/descargar', [TramiteEntregaController::class, 'downloadEvidence'])
        ->name('tramites.evidencias.descargar');
    Route::get('tramites/{tramite}/informes-cierre/{informe}/descargar', [TramiteEntregaController::class, 'downloadReport'])
        ->name('tramites.informes-cierre.descargar');

    Route::get('tramites/{tramite}/documentos-finales/{documento}/descargar', [TramiteDocumentoFinalController::class, 'download'])
        ->name('tramites.documento-final.descargar');
    Route::get('tramites/{tramite}/documentos/{documento}/descargar', [TramiteController::class, 'download'])
        ->name('tramites.documentos.descargar');
});

require __DIR__.'/settings.php';
