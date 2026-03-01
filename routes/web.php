<?php

use App\Http\Controllers\Admin\AllowListController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\BlackoutController;
use App\Http\Controllers\Admin\FacultyController;
use App\Http\Controllers\Admin\ProfessionalSchoolController;
use App\Http\Controllers\Admin\ReservationArtifactController;
use App\Http\Controllers\Admin\ReservationHistoryController;
use App\Http\Controllers\Admin\ReservationRequestController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Api\AdminAuditController;
use App\Http\Controllers\Api\AdminHistoryController;
use App\Http\Controllers\Api\AdminRequestsController;
use App\Http\Controllers\Api\PublicAvailabilityController;
use App\Http\Controllers\Api\StudentReservationsController;
use App\Http\Controllers\PublicCalendarController;
use App\Http\Controllers\ReservationPdfController;
use App\Http\Controllers\Student\CalendarController;
use App\Http\Controllers\Student\ReservationController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/calendario')->name('home');

Route::get('dashboard', function () {
    return \Inertia\Inertia::render('Dashboard');
})->middleware(['auth'])->name('dashboard');

Route::get('calendario', PublicCalendarController::class)->name('calendar.public');

Route::middleware(['auth'])->group(function () {
    Route::get('mi-calendario', CalendarController::class)->name('calendar.index');
    Route::get('reservas', [ReservationController::class, 'index'])->name('reservations.index');
    Route::get('reservas/nueva', [ReservationController::class, 'create'])->name('reservations.create');
    Route::post('reservas', [ReservationController::class, 'store'])->middleware('throttle:6,1')->name('reservations.store');
    Route::get('reservas/{reservation}/pdf', ReservationPdfController::class)
        ->can('viewPdf', 'reservation')
        ->name('reservations.pdf.show');
    Route::post('reservas/{reservation}/cancelar', [ReservationController::class, 'cancel'])
        ->middleware('throttle:6,1')
        ->can('cancel', 'reservation')
        ->name('reservations.cancel');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'permission:admin.panel.access'])->group(function () {
    Route::get('solicitudes', [ReservationRequestController::class, 'index'])
        ->middleware('permission:admin.reservas.solicitudes.view')
        ->name('requests.index');
    Route::post('solicitudes/{reservation}/aprobar', [ReservationRequestController::class, 'approve'])
        ->middleware('permission:admin.reservas.solicitudes.decide')
        ->name('requests.approve');
    Route::post('solicitudes/{reservation}/rechazar', [ReservationRequestController::class, 'reject'])
        ->middleware('permission:admin.reservas.solicitudes.decide')
        ->name('requests.reject');

    Route::get('historial', [ReservationHistoryController::class, 'index'])
        ->middleware('permission:admin.reservas.historial.view')
        ->name('history.index');

    Route::get('configuracion', [SettingsController::class, 'edit'])
        ->middleware('permission:admin.gestion.configuracion.manage')
        ->name('settings.edit');
    Route::put('configuracion', [SettingsController::class, 'update'])
        ->middleware('permission:admin.gestion.configuracion.manage')
        ->name('settings.update');

    Route::get('facultades', [FacultyController::class, 'index'])
        ->middleware('permission:admin.gestion.facultades.manage')
        ->name('faculties.index');
    Route::post('facultades', [FacultyController::class, 'store'])
        ->middleware('permission:admin.gestion.facultades.manage')
        ->name('faculties.store');
    Route::put('facultades/{faculty}', [FacultyController::class, 'update'])
        ->middleware('permission:admin.gestion.facultades.manage')
        ->name('faculties.update');

    Route::get('escuelas', [ProfessionalSchoolController::class, 'index'])
        ->middleware('permission:admin.gestion.escuelas.manage')
        ->name('schools.index');
    Route::post('escuelas', [ProfessionalSchoolController::class, 'store'])
        ->middleware('permission:admin.gestion.escuelas.manage')
        ->name('schools.store');
    Route::put('escuelas/{professionalSchool}', [ProfessionalSchoolController::class, 'update'])
        ->middleware('permission:admin.gestion.escuelas.manage')
        ->name('schools.update');

    Route::get('allow-list', [AllowListController::class, 'index'])
        ->middleware('permission:admin.gestion.allow_list.view')
        ->name('allow-list.index');
    Route::post('allow-list/importar', [AllowListController::class, 'import'])
        ->middleware('permission:admin.gestion.allow_list.import')
        ->name('allow-list.import');
    Route::get('allow-list/plantilla', [AllowListController::class, 'template'])
        ->middleware('permission:admin.gestion.allow_list.view')
        ->name('allow-list.template');

    Route::get('blackouts', [BlackoutController::class, 'index'])
        ->middleware('permission:admin.gestion.blackouts.manage')
        ->name('blackouts.index');
    Route::post('blackouts', [BlackoutController::class, 'store'])
        ->middleware('permission:admin.gestion.blackouts.manage')
        ->name('blackouts.store');
    Route::delete('blackouts/{blackout}', [BlackoutController::class, 'destroy'])
        ->middleware('permission:admin.gestion.blackouts.manage')
        ->name('blackouts.destroy');

    Route::get('auditoria', [AuditController::class, 'index'])
        ->middleware('permission:admin.supervision.auditoria.view')
        ->name('audit.index');

    Route::get('artifacts', [ReservationArtifactController::class, 'index'])
        ->middleware('permission:admin.reservas.reintentos.view')
        ->name('artifacts.index');
    Route::post('artifacts/{artifact}/retry', [ReservationArtifactController::class, 'retry'])
        ->middleware('permission:admin.reservas.reintentos.retry')
        ->name('artifacts.retry');
});

Route::prefix('api')->name('api.')->group(function () {
    Route::get('public/availability', PublicAvailabilityController::class)->name('public.availability');

    Route::middleware(['auth'])->group(function () {
        Route::get('student/reservations', StudentReservationsController::class)->name('student.reservations');
    });

    Route::middleware(['auth', 'permission:admin.panel.access'])->group(function () {
        Route::get('admin/requests', AdminRequestsController::class)
            ->middleware('permission:admin.reservas.solicitudes.view')
            ->name('admin.requests');
        Route::get('admin/history', AdminHistoryController::class)
            ->middleware('permission:admin.reservas.historial.view')
            ->name('admin.history');
        Route::get('admin/audit', AdminAuditController::class)
            ->middleware('permission:admin.supervision.auditoria.view')
            ->name('admin.audit');
    });
});

require __DIR__.'/settings.php';
