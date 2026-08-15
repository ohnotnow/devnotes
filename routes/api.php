<?php

use App\Http\Controllers\Api\V1\NoteController;
use App\Http\Controllers\Api\V1\TeamController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->middleware('auth:sanctum')->group(function () {
    Route::apiResource('notes', NoteController::class);
    Route::get('teams', [TeamController::class, 'index'])->name('teams.index');
});
