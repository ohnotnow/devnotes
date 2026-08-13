<?php

use App\Livewire\Admin\Users;
use App\Livewire\ApiTokens;
use App\Livewire\NoteShow;
use App\Livewire\NotesIndex;
use Illuminate\Support\Facades\Route;

require __DIR__.'/sso-auth.php';
Route::middleware('auth')->group(function () {
    Route::get('/', NotesIndex::class)->name('home');
    Route::get('/notes/{note}', NoteShow::class)->name('notes.show');
    Route::get('/settings/api-tokens', ApiTokens::class)->name('api-tokens');

    Route::get('/admin/users', Users::class)->name('admin.users')->middleware('can:admin');
});
