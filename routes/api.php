<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\CpuController;
use App\Http\Controllers\MemoryController;
use App\Http\Controllers\DiskController;
use App\Http\Controllers\AllController;
use App\Http\Controllers\IncidentController;

Route::prefix('v1')->group(function () {
    Route::get('/health',    [HealthController::class,   'health']);
    Route::get('/cpu',       [CpuController::class,      'cpu']);
    Route::get('/memory',    [MemoryController::class,   'memory']);
    Route::get('/disk',      [DiskController::class,     'disk']);
    Route::get('/all',       [AllController::class,      'all']);
    Route::get('/incidents', [IncidentController::class, 'incidents']);
});