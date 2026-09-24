<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BlockController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\FacultyController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\FingerprintController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/', [AuthController::class, 'create'])->name('login');

    Route::post('/login', [AuthController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])
        ->name('logout');

    Route::get('/dashboard', DashboardController::class)
        ->name('dashboard');

    /*
    |--------------------------------------------------------------------------
    | Admin and faculty routes
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:admin,faculty,program_head')->group(function () {
        Route::get('/students', [StudentController::class, 'index'])
            ->name('students.index');

        Route::get('/students/{student}', [StudentController::class, 'show'])
            ->whereNumber('student')
            ->name('students.show');

        Route::get('/blocks', [BlockController::class, 'index'])
            ->name('blocks.index');

        Route::get('/schedules', [ScheduleController::class, 'index'])
            ->name('schedules.index');
    });

    /*
    |--------------------------------------------------------------------------
    | Administrator-only routes
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:admin')->group(function () {
        Route::get('/students/create', [StudentController::class, 'create'])
            ->name('students.create');

        Route::post('/students', [StudentController::class, 'store'])
            ->name('students.store');

        Route::get('/students/{student}/edit', [StudentController::class, 'edit'])
            ->whereNumber('student')
            ->name('students.edit');

        Route::patch('/students/{student}', [StudentController::class, 'update'])
            ->whereNumber('student')
            ->name('students.update');

        Route::delete('/students/{student}', [StudentController::class, 'destroy'])
            ->whereNumber('student')
            ->name('students.destroy');

        Route::post('/blocks', [BlockController::class, 'store'])
            ->name('blocks.store');

        Route::delete('/blocks/{block}', [BlockController::class, 'destroy'])
            ->whereNumber('block')
            ->name('blocks.destroy');

        Route::post('/schedules', [ScheduleController::class, 'store'])
            ->name('schedules.store');

        Route::delete('/schedules/{schedule}', [ScheduleController::class, 'destroy'])
            ->whereNumber('schedule')
            ->name('schedules.destroy');
    });
});

/*
   |--------------------------------------------------------------------------
   | Program Head routes
   |--------------------------------------------------------------------------
   */
Route::middleware([
    'auth',
    'role:admin,program_head',
])
    ->prefix('program-head')
    ->name('program-head.')
    ->group(function () {
        Route::get(
            '/subjects',
            [SubjectController::class, 'index']
        )->name('subjects.index');

        Route::patch(
            '/subjects/{subject}/faculty',
            [SubjectController::class, 'assignFaculty']
        )
            ->whereNumber('subject')
            ->name('subjects.assign-faculty');

        Route::get(
            '/students/{student}/enroll',
            [EnrollmentController::class, 'create']
        )
            ->whereNumber('student')
            ->name('students.enroll');

        Route::post(
            '/students/{student}/enroll',
            [EnrollmentController::class, 'store']
        )
            ->whereNumber('student')
            ->name('students.enroll.store');

        Route::get(
            '/subjects/{subject}/enroll',
            [EnrollmentController::class, 'createForSubject']
        )
            ->whereNumber('subject')
            ->name('subjects.enroll');

        Route::post(
            '/subjects/{subject}/enroll',
            [EnrollmentController::class, 'storeForSubject']
        )
            ->whereNumber('subject')
            ->name('subjects.enroll.store');

        Route::get(
            '/faculty',
            [FacultyController::class, 'index']
        )->name('faculty.index');

        Route::post(
            '/faculty',
            [FacultyController::class, 'store']
        )->name('faculty.store');

        Route::delete(
            '/faculty/{user}',
            [FacultyController::class, 'destroy']
        )
            ->whereNumber('user')
            ->name('faculty.destroy');
    });

Route::get(
    '/program-head/faculty-loads',
    [FacultyController::class, 'index']
)
    ->middleware(['auth', 'role:admin,program_head,faculty'])
    ->name('program-head.faculty-loads.index');



Route::middleware('auth')->group(function () {
    Route::view('/fingerprint/register', 'fingerprint-register');

    Route::post('/fingerprint/enroll', [FingerprintController::class, 'start']);
    Route::get('/fingerprint/enroll/{enrollment}/status',
        [FingerprintController::class, 'status']);
});