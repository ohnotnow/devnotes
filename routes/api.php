<?php

use App\Http\Controllers\Api\V1\ExportController;
use App\Http\Controllers\Api\V1\NoteController;
use App\Http\Controllers\Api\V1\TeamController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::apiResource('notes', NoteController::class)->scoped(['note' => 'code'])->withTrashed(['show']);
    Route::get('teams', [TeamController::class, 'index'])->name('teams.index');
    Route::get('export', ExportController::class)->name('export')->middleware('can:admin');
});
