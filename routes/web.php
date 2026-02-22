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
use App\Http\Controllers\Api\AdminHistoryController;
use App\Http\Controllers\Api\AdminRequestsController;
use App\Http\Controllers\Api\PublicAvailabilityController;
use App\Http\Controllers\Api\StudentReservationsController;
use App\Http\Controllers\PublicCalendarController;
use App\Http\Controllers\ReservationPdfController;
use App\Http\Controllers\Student\ReservationController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/calendario')->name('home');

Route::get('dashboard', function () {
    return \Inertia\Inertia::render('Dashboard');
})->middleware(['auth'])->name('dashboard');

Route::get('calendario', PublicCalendarController::class)->name('calendar.public');

Route::middleware(['auth'])->group(function () {
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

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('solicitudes', [ReservationRequestController::class, 'index'])->name('requests.index');
    Route::post('solicitudes/{reservation}/aprobar', [ReservationRequestController::class, 'approve'])->name('requests.approve');
    Route::post('solicitudes/{reservation}/rechazar', [ReservationRequestController::class, 'reject'])->name('requests.reject');
    Route::get('historial', [ReservationHistoryController::class, 'index'])->name('history.index');

    Route::get('configuracion', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('configuracion', [SettingsController::class, 'update'])->name('settings.update');

    Route::get('facultades', [FacultyController::class, 'index'])->name('faculties.index');
    Route::post('facultades', [FacultyController::class, 'store'])->name('faculties.store');
    Route::put('facultades/{faculty}', [FacultyController::class, 'update'])->name('faculties.update');

    Route::get('escuelas', [ProfessionalSchoolController::class, 'index'])->name('schools.index');
    Route::post('escuelas', [ProfessionalSchoolController::class, 'store'])->name('schools.store');
    Route::put('escuelas/{professionalSchool}', [ProfessionalSchoolController::class, 'update'])->name('schools.update');

    Route::get('allow-list', [AllowListController::class, 'index'])->name('allow-list.index');
    Route::post('allow-list/importar', [AllowListController::class, 'import'])->name('allow-list.import');

    Route::get('blackouts', [BlackoutController::class, 'index'])->name('blackouts.index');
    Route::post('blackouts', [BlackoutController::class, 'store'])->name('blackouts.store');
    Route::delete('blackouts/{blackout}', [BlackoutController::class, 'destroy'])->name('blackouts.destroy');

    Route::get('auditoria', [AuditController::class, 'index'])->name('audit.index');

    Route::get('artifacts', [ReservationArtifactController::class, 'index'])->name('artifacts.index');
    Route::post('artifacts/{artifact}/retry', [ReservationArtifactController::class, 'retry'])->name('artifacts.retry');
});

Route::prefix('api')->name('api.')->group(function () {
    Route::get('public/availability', PublicAvailabilityController::class)->name('public.availability');

    Route::middleware(['auth'])->group(function () {
        Route::get('student/reservations', StudentReservationsController::class)->name('student.reservations');
    });

    Route::middleware(['auth', 'admin'])->group(function () {
        Route::get('admin/requests', AdminRequestsController::class)->name('admin.requests');
        Route::get('admin/history', AdminHistoryController::class)->name('admin.history');
    });
});

require __DIR__.'/settings.php';
