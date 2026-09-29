<?php

use App\Http\Controllers\Api\Esp8266Controller;
use App\Http\Controllers\Api\FrontendController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\ReadingController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\SuperAdminController;
use App\Http\Controllers\FingerprintController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [SessionController::class, 'login']);
Route::get('/health/status', [HealthController::class, 'status']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/auth/me', [SessionController::class, 'me']);
    Route::put('/auth/password', [SessionController::class, 'updatePassword']);
    Route::post('/auth/logout', [SessionController::class, 'logout']);

    Route::middleware(['active', 'role:student'])->prefix('students')->group(function (): void {
        Route::get('/profile', [FrontendController::class, 'studentProfile']);
        Route::put('/profile', [FrontendController::class, 'updateStudentProfile']);
        Route::put('/password', [FrontendController::class, 'updateStudentPassword']);
        Route::get('/attendance', [FrontendController::class, 'studentAttendance']);
        Route::get('/courses', [FrontendController::class, 'studentCourses']);
    });

    Route::middleware(['active', 'password.changed', 'role:admin,faculty'])->group(function (): void {
        Route::get('/admin/reports', [FrontendController::class, 'attendanceReports']);
        Route::get('/admin/schedule', [FrontendController::class, 'facultySchedule']);
        Route::put('/admin/classes/{subject}/{block}/grace-period', [FrontendController::class, 'updateClassGracePeriod'])
            ->whereNumber(['subject', 'block']);
        Route::post('/admin/archive-requests', [FrontendController::class, 'storeArchiveRequest']);
    });

    Route::middleware(['active', 'password.changed', 'role:super_admin'])->group(function (): void {
        Route::patch('/admin/archive-requests/{archiveRequest}', [FrontendController::class, 'updateArchiveRequest'])
            ->whereNumber('archiveRequest');
        Route::get('/admin/master-schedule', [SuperAdminController::class, 'masterSchedule']);
        Route::post('/admin/master-schedule', [SuperAdminController::class, 'storeMasterSchedule']);
        Route::post('/admin/master-schedule/sync', [SuperAdminController::class, 'syncMasterSchedule']);
        Route::delete('/admin/master-schedule/{entry}', [SuperAdminController::class, 'destroyMasterSchedule'])->whereNumber('entry');
        Route::get('/admin/users', [SuperAdminController::class, 'users']);
        Route::post('/admin/users', [SuperAdminController::class, 'storeUser']);
        Route::patch('/admin/users/{user}', [SuperAdminController::class, 'updateUser'])->whereNumber('user');
        Route::get('/admin/available-blocks', [SuperAdminController::class, 'availableBlocks']);
        Route::get('/admin/schedule-change-requests', [SuperAdminController::class, 'scheduleChangeRequests']);
        Route::patch('/admin/schedule-change-requests/{changeRequest}', [SuperAdminController::class, 'updateScheduleChangeRequest'])->whereNumber('changeRequest');
        Route::get('/admin/audit-logs', [SuperAdminController::class, 'auditLogs']);
        Route::get('/admin/app-reports', [SuperAdminController::class, 'appReports']);
        Route::patch('/admin/app-reports/{report}', [SuperAdminController::class, 'updateAppReport'])->whereNumber('report');
    });
});

Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
    Route::get('/health', HealthController::class);
    Route::post('/students', [Esp8266Controller::class, 'registerStudent']);
    Route::post('/readings', [Esp8266Controller::class, 'recordAttendance']);
    Route::post('/attendance', [Esp8266Controller::class, 'recordAttendance']);
    Route::post('/telemetry', [ReadingController::class, 'store']);
    Route::get('/fingerprint/enroll/pending', [FingerprintController::class, 'pending']);
    Route::post('/fingerprint/enroll/{enrollment}/complete', [FingerprintController::class, 'complete'])
        ->whereNumber('enrollment');

    Route::middleware(['password.changed', 'role:admin,faculty,super_admin'])->group(function (): void {
        Route::get('/admin/overview', [FrontendController::class, 'adminOverview']);
        Route::get('/admin/archive-requests', [FrontendController::class, 'archiveRequests']);
    });
});
