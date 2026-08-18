<?php

use App\Http\Controllers\Admin\ExportController;
use App\Http\Middleware\RequirePasswordChange;
use App\Livewire\Admin\ActivityLog;
use App\Livewire\Admin\ImportNotes;
use App\Livewire\Admin\Teams;
use App\Livewire\Admin\Users;
use App\Livewire\ApiTokens;
use App\Livewire\ChangePassword;
use App\Livewire\NoteShow;
use App\Livewire\NotesIndex;
use App\Livewire\TeamSettings;
use App\Livewire\TidyNotes;
use Illuminate\Support\Facades\Route;

require __DIR__.'/sso-auth.php';
Route::middleware(['auth', RequirePasswordChange::class])->group(function () {
    Route::get('/', NotesIndex::class)->name('home');
    Route::get('/notes/{note:code}', NoteShow::class)->name('notes.show')->withTrashed();
    Route::get('/tidy', TidyNotes::class)->name('tidy');
    Route::get('/settings/api-tokens', ApiTokens::class)->name('api-tokens');
    Route::get('/settings/teams', TeamSettings::class)->name('team-settings');
    Route::get('/settings/password', ChangePassword::class)->name('change-password');

    Route::get('/admin/users', Users::class)->name('admin.users')->middleware('can:admin');
    Route::get('/admin/teams', Teams::class)->name('admin.teams')->middleware('can:admin');
    Route::get('/admin/export', ExportController::class)->name('admin.export')->middleware('can:admin');
    Route::get('/admin/import', ImportNotes::class)->name('admin.import')->middleware('can:admin');
    Route::get('/admin/activity', ActivityLog::class)->name('admin.activity')->middleware('can:admin');
});
