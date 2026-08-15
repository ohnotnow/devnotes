<?php

use App\Jobs\ImportNotes;
use App\Models\Note;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

it('recreates exported notes with their original ids, authors, teams, timestamps and deleted state', function () {
    Storage::fake('local');
    Storage::disk('local')->put('imports/export.json', file_get_contents(base_path('tests/fixtures/export-v1.json')));

    $report = (new ImportNotes('local', 'imports/export.json'))->handle();

    expect(Note::withTrashed()->orderBy('id')->pluck('id')->all())->toBe([1, 4, 7]);
    $teamNote = Note::find(4);
    expect($teamNote->title)->toBe('How to install the puppet client on Rocky Linux');
    expect($teamNote->user->email)->toBe('author@example.com');
    expect($teamNote->user->is_admin)->toBeFalse();
    expect($teamNote->user->is_staff)->toBeTrue();
    expect($teamNote->teams->pluck('name')->sort()->values()->all())->toBe(['developers', 'sysadmins']);
    expect($teamNote->created_at->toJSON())->toBe('2026-08-15T10:00:00.000000Z');
    expect($teamNote->updated_at->toJSON())->toBe('2026-08-15T10:00:00.000000Z');
    expect(Note::find(1)->teams)->toBeEmpty();
    $deletedNote = Note::withTrashed()->find(7);
    expect($deletedNote->deleted_at->toJSON())->toBe('2026-08-15T10:00:00.000000Z');
    expect($deletedNote->user->is_admin)->toBeTrue();
    expect($deletedNote->teams)->toBeEmpty();
    expect(User::count())->toBe(2);
    expect(Team::orderBy('name')->pluck('name')->all())->toBe(['developers', 'sysadmins']);
    expect($report)->toBe(['imported' => 3, 'skipped' => [], 'users_created' => 2, 'teams_created' => 2]);
});

it('imports nothing on a re-import and reports every id as skipped', function () {
    Storage::fake('local');
    $fixture = file_get_contents(base_path('tests/fixtures/export-v1.json'));
    Storage::disk('local')->put('imports/first.json', $fixture);
    (new ImportNotes('local', 'imports/first.json'))->handle();

    Storage::disk('local')->put('imports/second.json', $fixture);
    $report = (new ImportNotes('local', 'imports/second.json'))->handle();

    expect($report)->toBe(['imported' => 0, 'skipped' => [1, 4, 7], 'users_created' => 0, 'teams_created' => 0]);
    expect(Note::withTrashed()->count())->toBe(3);
    expect(User::count())->toBe(2);
    expect(Team::count())->toBe(2);
});

it('assigns unknown-author notes to the fallback owner instead of creating users when told not to', function () {
    Storage::fake('local');
    Storage::disk('local')->put('imports/export.json', file_get_contents(base_path('tests/fixtures/export-v1.json')));
    $importingAdmin = User::factory()->admin()->create(['email' => 'importer@example.com']);
    User::factory()->create(['email' => 'author@example.com']);

    $report = (new ImportNotes('local', 'imports/export.json', createUsers: false, fallbackOwner: $importingAdmin))->handle();

    expect(User::count())->toBe(2);
    expect($report['users_created'])->toBe(0);
    expect(Note::find(1)->user->email)->toBe('author@example.com');
    expect(Note::find(4)->user->email)->toBe('author@example.com');
    expect(Note::withTrashed()->find(7)->user->email)->toBe('importer@example.com');
});

it('makes live imported notes searchable but not trashed ones', function () {
    Storage::fake('local');
    Storage::disk('local')->put('imports/export.json', file_get_contents(base_path('tests/fixtures/export-v1.json')));

    (new ImportNotes('local', 'imports/export.json'))->handle();

    expect(Note::search('teamless')->get()->pluck('id')->all())->toBe([1]);
    expect(Note::search('soft-deleted')->get())->toBeEmpty();
});

it('deletes its stored working copy on success and on failure', function () {
    Storage::fake('local');
    Storage::disk('local')->put('imports/good.json', file_get_contents(base_path('tests/fixtures/export-v1.json')));
    Storage::disk('local')->put('imports/bad.json', json_encode(['version' => 99, 'notes' => []]));

    (new ImportNotes('local', 'imports/good.json'))->handle();
    expect(fn () => (new ImportNotes('local', 'imports/bad.json'))->handle())
        ->toThrow(RuntimeException::class, 'Unsupported export version');

    Storage::disk('local')->assertMissing('imports/good.json');
    Storage::disk('local')->assertMissing('imports/bad.json');
});
