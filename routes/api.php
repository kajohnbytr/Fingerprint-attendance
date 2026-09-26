<?php

use App\Http\Controllers\Api\Esp8266Controller;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\ReadingController;
use App\Http\Controllers\FingerprintController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
    Route::get('/health', HealthController::class);
    Route::post('/students', [Esp8266Controller::class, 'registerStudent']);
    Route::post('/readings', [Esp8266Controller::class, 'recordAttendance']);
    Route::post('/attendance', [Esp8266Controller::class, 'recordAttendance']);
    Route::post('/telemetry', [ReadingController::class, 'store']);
    Route::get('/fingerprint/enroll/pending', [FingerprintController::class, 'pending']);
    Route::post('/fingerprint/enroll/{enrollment}/complete', [FingerprintController::class, 'complete'])
        ->whereNumber('enrollment');
});
