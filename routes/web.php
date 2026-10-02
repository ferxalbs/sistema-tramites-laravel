<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\InstitutionalPositionController;
use App\Http\Controllers\InstitutionalRecipientController;
use App\Http\Controllers\OutputDocumentTypeController;
use App\Http\Controllers\RegistrationVerificationController;
use App\Http\Controllers\TeacherAccessRequestController;
use App\Http\Controllers\TramiteAsignacionController;
use App\Http\Controllers\TramiteBorradorController;
use App\Http\Controllers\TramiteClassificationController;
use App\Http\Controllers\TramiteController;
use App\Http\Controllers\TramiteDocumentoFinalController;
use App\Http\Controllers\TramiteEntregaAdminController;
use App\Http\Controllers\TramiteEntregaController;
use App\Http\Controllers\TramiteEstudianteController;
use App\Http\Controllers\TramiteNotificacionController;
use App\Http\Controllers\TramitePlantillaController;
use App\Http\Controllers\TramiteReportController;
use App\Http\Controllers\TramiteRevisionController;
use App\Http\Controllers\TramiteTypeController;
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
Route::get('solicitud-docente', [TeacherAccessRequestController::class, 'create'])->middleware('guest')->name('teacher-access.create');
Route::post('solicitud-docente', [TeacherAccessRequestController::class, 'store'])->middleware(['guest', 'throttle:6,1'])->name('teacher-access.store');
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
    Route::get('notificaciones', [TramiteNotificacionController::class, 'index'])
        ->middleware('role:estudiante,docente,administrador')
        ->name('notificaciones.index');
    Route::patch('notificaciones/{notification}/leer', [TramiteNotificacionController::class, 'read'])
        ->middleware('role:estudiante,docente,administrador')
        ->whereNumber('notification')
        ->name('notificaciones.read');
    Route::patch('notificaciones/leer-todas', [TramiteNotificacionController::class, 'readAll'])
        ->middleware('role:estudiante,docente,administrador')
        ->name('notificaciones.read-all');

    Route::get('dashboard', DashboardController::class)
        ->middleware('role:estudiante,docente,administrador')
        ->name('dashboard');

    Route::get('buscar', GlobalSearchController::class)
        ->middleware('role:estudiante,docente,administrador')
        ->name('search.index');

    Route::middleware('role:administrador')->group(function (): void {
        Route::get('admin/reportes', [TramiteReportController::class, 'index'])->name('admin.reports.index');
        Route::post('tramites/iniciar', [TramiteController::class, 'start'])->name('tramites.start');
        Route::resource('tramites', TramiteController::class)->only(['index', 'create', 'store', 'show']);
        Route::get('tramites/{tramite}/comprobante', [TramiteController::class, 'receipt'])
            ->name('tramites.receipt');
        Route::get('tramites/{tramite}/borradores/crear', [TramiteBorradorController::class, 'create'])
            ->name('tramites.borradores.create');
        Route::get('tramites/{tramite}/borradores/{borrador}', [TramiteBorradorController::class, 'show'])
            ->whereNumber('borrador')->name('tramites.borradores.show');
        Route::get('tramites/{tramite}/borradores/{borrador}/pdf', [TramiteBorradorController::class, 'pdf'])
            ->whereNumber('borrador')->name('tramites.borradores.pdf');
        Route::post('tramites/{tramite}/borradores', [TramiteBorradorController::class, 'store'])
            ->name('tramites.borradores.store');
        Route::post('tramites/{tramite}/preparar-asignacion', [TramiteBorradorController::class, 'prepareAssignment'])
            ->name('tramites.asignacion.prepare');
    });

    Route::middleware('role:administrador')->group(function (): void {
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
        Route::post('tramites/{tramite}/entrega/{entrega}/anular', [TramiteEntregaController::class, 'annul'])
            ->name('tramites.entrega.anular');
        Route::post('tramites/{tramite}/documentos-finales/{documento}/anular', [TramiteDocumentoFinalController::class, 'annul'])
            ->name('tramites.documento-final.anular');
        Route::post('tramites/{tramite}/entrega/reabrir', [TramiteEntregaController::class, 'reopen'])
            ->name('tramites.entrega.reabrir');
        Route::get('admin/cargos', [InstitutionalPositionController::class, 'index'])->name('admin.positions.index');
        Route::patch('admin/cargos/{position}', [InstitutionalPositionController::class, 'update'])
            ->whereNumber('position')->name('admin.positions.update');
        Route::get('admin/destinatarios', [InstitutionalRecipientController::class, 'index'])->name('admin.recipients.index');
        Route::post('admin/destinatarios', [InstitutionalRecipientController::class, 'store'])->name('admin.recipients.store');
        Route::patch('admin/destinatarios/{recipient}', [InstitutionalRecipientController::class, 'update'])
            ->whereNumber('recipient')->name('admin.recipients.update');
        Route::get('admin/tipos-tramite', [TramiteTypeController::class, 'index'])->name('admin.types.index');
        Route::patch('admin/tipos-tramite/{type}', [TramiteTypeController::class, 'update'])
            ->whereNumber('type')->name('admin.types.update');
        Route::get('admin/clasificaciones', [TramiteClassificationController::class, 'index'])->name('admin.classifications.index');
        Route::patch('admin/clasificaciones/{classification}', [TramiteClassificationController::class, 'update'])
            ->whereNumber('classification')->name('admin.classifications.update');
        Route::get('admin/formatos-salida', [OutputDocumentTypeController::class, 'index'])->name('admin.output-formats.index');
        Route::patch('admin/formatos-salida/{format}', [OutputDocumentTypeController::class, 'update'])
            ->whereNumber('format')->name('admin.output-formats.update');
        Route::get('admin/plantillas', [TramitePlantillaController::class, 'index'])->name('admin.templates.index');
        Route::post('admin/plantillas', [TramitePlantillaController::class, 'store'])->name('admin.templates.store');
        Route::post('admin/plantillas/{plantilla}/versiones', [TramitePlantillaController::class, 'version'])
            ->name('admin.templates.version');
        Route::patch('admin/plantillas/{plantilla}/estado', [TramitePlantillaController::class, 'state'])
            ->name('admin.templates.state');
        Route::get('admin/plantillas/{plantilla}/campos', [TramitePlantillaController::class, 'fields'])
            ->name('admin.templates.fields');
        Route::patch('admin/plantillas/{plantilla}/campos/{field}', [TramitePlantillaController::class, 'updateField'])
            ->whereNumber('field')->name('admin.templates.fields.update');
        Route::patch('admin/plantillas/{plantilla}/campos/{field}/mover', [TramitePlantillaController::class, 'moveField'])
            ->whereNumber('field')->name('admin.templates.fields.move');
        Route::get('admin/usuarios', [UserAccountController::class, 'index'])->name('admin.users.index');
        Route::get('admin/usuarios/crear', [UserAccountController::class, 'create'])->name('admin.users.create');
        Route::post('admin/usuarios', [UserAccountController::class, 'store'])->name('admin.users.store');
        Route::get('admin/usuarios/{user}/editar', [UserAccountController::class, 'edit'])->name('admin.users.edit');
        Route::put('admin/usuarios/{user}', [UserAccountController::class, 'save'])->name('admin.users.save');
        Route::patch('admin/usuarios/{user}/estado', [UserAccountController::class, 'update'])->name('admin.users.update');
        Route::delete('admin/usuarios/{user}', [UserAccountController::class, 'destroy'])->name('admin.users.destroy');
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

    Route::middleware('role:administrador')->group(function (): void {
        Route::get('tramites/{tramite}/entrega', [TramiteEntregaController::class, 'show'])->name('tramites.entrega.show');
        Route::post('tramites/{tramite}/entrega/preparar', [TramiteEntregaController::class, 'prepare'])->name('tramites.entrega.prepare');
        Route::post('tramites/{tramite}/entrega/firma', [TramiteEntregaController::class, 'registerSignature'])->name('tramites.entrega.firma');
        Route::post('tramites/{tramite}/entrega/registrar', [TramiteEntregaController::class, 'registerDelivery'])->name('tramites.entrega.registrar');
        Route::post('tramites/{tramite}/entrega/cerrar', [TramiteEntregaController::class, 'close'])->name('tramites.entrega.cerrar');
    });

    Route::post('tramites/{tramite}/entrega/confirmar', [TramiteEntregaController::class, 'confirm'])
        ->middleware('role:administrador,estudiante')
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
