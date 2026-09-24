<?php

use App\Models\Reading;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\Esp8266Controller;
use Illuminate\Support\Facades\Route;

Route::post('/readings', function (Request $request) {
    $data = $request->validate([
        'device_id' => ['required', 'string', 'max:100'],
        'temperature' => ['required', 'numeric'],
        'humidity' => ['required', 'numeric', 'between:0,100'],
    ]);

    if (! $request->user()->tokenCan('readings:create')) {
        return response()->json(['message' => 'Forbidden'], 403);
    }

    $reading = new Reading();
    $reading->device_id = $data['device_id'];
    $reading->temperature = $data['temperature'];
    $reading->humidity = $data['humidity'];
    $reading->save();

    return response()->json(['message' => 'Saved', 'id' => $reading->id], 201);
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/students', [Esp8266Controller::class, 'registerStudent']);
    Route::post('/readings', [Esp8266Controller::class, 'recordAttendance']);
});