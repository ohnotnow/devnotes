<?php

use App\Enums\ActivityAction;
use App\Jobs\ImportNotes;
use App\Models\Activity;
use App\Models\Note;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

it('recreates exported notes with their ulids, codes, authors, teams, timestamps and deleted state', function () {
    Storage::fake('local');
    Storage::disk('local')->put('imports/export.json', file_get_contents(base_path('tests/fixtures/export-v1.json')));

    $report = (new ImportNotes('local', 'imports/export.json'))->handle();

    expect(Note::withTrashed()->count())->toBe(3);
    $teamNote = Note::where('code', 'abq4x')->sole();
    expect($teamNote->ulid)->toBe('01ARZ3NDEKTSV4RRFFQ69G5FA2');
    expect($teamNote->title)->toBe('How to install the puppet client on Rocky Linux');
    expect($teamNote->user->email)->toBe('author@example.com');
    expect($teamNote->user->is_admin)->toBeFalse();
    expect($teamNote->user->is_staff)->toBeTrue();
    expect($teamNote->teams->pluck('name')->sort()->values()->all())->toBe(['developers', 'sysadmins']);
    expect($teamNote->created_at->toJSON())->toBe('2026-08-15T10:00:00.000000Z');
    expect($teamNote->updated_at->toJSON())->toBe('2026-08-15T10:00:00.000000Z');
    expect(Note::where('code', 'aq2b3')->sole()->teams)->toBeEmpty();
    $deletedNote = Note::withTrashed()->where('code', 'zde77')->sole();
    expect($deletedNote->deleted_at->toJSON())->toBe('2026-08-15T10:00:00.000000Z');
    expect($deletedNote->user->is_admin)->toBeTrue();
    expect($deletedNote->teams)->toBeEmpty();
    expect(User::count())->toBe(2);
    expect(Team::orderBy('name')->pluck('name')->all())->toBe(['developers', 'sysadmins']);
    expect($report)->toBe(['imported' => 3, 'skipped' => [], 'overwritten' => [], 'recoded' => [], 'users_created' => 2, 'teams_created' => 2]);
});

it('recreates read data and activity history from the export file', function () {
    Storage::fake('local');
    Storage::disk('local')->put('imports/export.json', file_get_contents(base_path('tests/fixtures/export-v1.json')));

    (new ImportNotes('local', 'imports/export.json'))->handle();

    $teamNote = Note::where('code', 'abq4x')->sole();
    expect($teamNote->read_count)->toBe(3);
    expect($teamNote->last_read_at->toJSON())->toBe('2026-08-15T09:00:00.000000Z');
    expect($teamNote->activities)->toHaveCount(2);
    $createdActivity = $teamNote->activities()->where('ulid', '01ARZ3NDEKTSV4RRFFQ69G5AA1')->sole();
    expect($createdActivity->user->email)->toBe('author@example.com');
    expect($createdActivity->action)->toBe(ActivityAction::Created);
    expect($createdActivity->description)->toBe("created note #abq4x 'How to install the puppet client on Rocky Linux'");
    expect($createdActivity->created_at->toJSON())->toBe('2026-08-15T10:00:00.000000Z');
    expect(Activity::count())->toBe(2);
});

it('merges no duplicate activity history on a re-import, keyed on the activity ulid', function () {
    Storage::fake('local');
    $fixture = file_get_contents(base_path('tests/fixtures/export-v1.json'));
    Storage::disk('local')->put('imports/first.json', $fixture);
    (new ImportNotes('local', 'imports/first.json'))->handle();

    Storage::disk('local')->put('imports/second.json', $fixture);
    (new ImportNotes('local', 'imports/second.json'))->handle();

    expect(Activity::count())->toBe(2);
    expect(Note::where('code', 'abq4x')->sole()->activities)->toHaveCount(2);
});

it('imports an older export file that predates read data and activity history', function () {
    Storage::fake('local');
    $payload = json_decode(file_get_contents(base_path('tests/fixtures/export-v1.json')), true);
    $payload['notes'] = array_map(function (array $note): array {
        unset($note['read_count'], $note['last_read_at'], $note['activities']);

        return $note;
    }, $payload['notes']);
    Storage::disk('local')->put('imports/old.json', json_encode($payload));

    $report = (new ImportNotes('local', 'imports/old.json'))->handle();

    expect($report['imported'])->toBe(3);
    $teamNote = Note::where('code', 'abq4x')->sole();
    expect($teamNote->read_count)->toBe(0);
    expect($teamNote->last_read_at)->toBeNull();
    expect(Activity::count())->toBe(0);
});

it('imports nothing on a re-import and reports every code as skipped', function () {
    Storage::fake('local');
    $fixture = file_get_contents(base_path('tests/fixtures/export-v1.json'));
    Storage::disk('local')->put('imports/first.json', $fixture);
    (new ImportNotes('local', 'imports/first.json'))->handle();

    Storage::disk('local')->put('imports/second.json', $fixture);
    $report = (new ImportNotes('local', 'imports/second.json'))->handle();

    expect($report)->toBe(['imported' => 0, 'skipped' => ['aq2b3', 'abq4x', 'zde77'], 'overwritten' => [], 'recoded' => [], 'users_created' => 0, 'teams_created' => 0]);
    expect(Note::withTrashed()->count())->toBe(3);
    expect(User::count())->toBe(2);
    expect(Team::count())->toBe(2);
});

it('overwrites a matched note from the file when its ulid is in the overwrite list', function () {
    Storage::fake('local');
    $fixture = file_get_contents(base_path('tests/fixtures/export-v1.json'));
    Storage::disk('local')->put('imports/first.json', $fixture);
    (new ImportNotes('local', 'imports/first.json'))->handle();
    $driftedNote = Note::where('code', 'abq4x')->sole();
    $driftedNote->update(['title' => 'Drifted title', 'body' => 'Drifted body']);
    $driftedNote->teams()->detach();

    Storage::disk('local')->put('imports/second.json', $fixture);
    $report = (new ImportNotes('local', 'imports/second.json', overwriteUlids: ['01ARZ3NDEKTSV4RRFFQ69G5FA2']))->handle();

    expect($report['overwritten'])->toBe(['abq4x']);
    expect($report['skipped'])->toBe(['aq2b3', 'zde77']);
    expect($report['imported'])->toBe(0);
    $restoredNote = Note::where('code', 'abq4x')->sole();
    expect($restoredNote->title)->toBe('How to install the puppet client on Rocky Linux');
    expect($restoredNote->teams->pluck('name')->sort()->values()->all())->toBe(['developers', 'sysadmins']);
    expect($restoredNote->updated_at->toJSON())->toBe('2026-08-15T10:00:00.000000Z');
    expect($restoredNote->ulid)->toBe('01ARZ3NDEKTSV4RRFFQ69G5FA2');
    expect(Note::withTrashed()->count())->toBe(3);
});

it('re-mints the code of a genuinely new note whose code is already taken', function () {
    Storage::fake('local');
    Storage::disk('local')->put('imports/export.json', file_get_contents(base_path('tests/fixtures/export-v1.json')));
    $localStranger = Note::factory()->create(['code' => 'abq4x', 'title' => 'A local stranger']);

    $report = (new ImportNotes('local', 'imports/export.json'))->handle();

    expect(Note::withTrashed()->count())->toBe(4);
    expect($report['imported'])->toBe(3);
    expect($report['recoded'])->toHaveCount(1);
    expect($report['recoded'][0]['old'])->toBe('abq4x');
    expect($report['recoded'][0]['new'])->not->toBe('abq4x');
    $incomingNote = Note::where('code', $report['recoded'][0]['new'])->sole();
    expect($incomingNote->title)->toBe('How to install the puppet client on Rocky Linux');
    expect($incomingNote->ulid)->toBe('01ARZ3NDEKTSV4RRFFQ69G5FA2');
    expect($localStranger->fresh()->title)->toBe('A local stranger');
    expect($localStranger->fresh()->code)->toBe('abq4x');
});

it('assigns unknown-author notes to the fallback owner instead of creating users when told not to', function () {
    Storage::fake('local');
    Storage::disk('local')->put('imports/export.json', file_get_contents(base_path('tests/fixtures/export-v1.json')));
    $importingAdmin = User::factory()->admin()->create(['email' => 'importer@example.com']);
    User::factory()->create(['email' => 'author@example.com']);

    $report = (new ImportNotes('local', 'imports/export.json', createUsers: false, fallbackOwner: $importingAdmin))->handle();

    expect(User::count())->toBe(2);
    expect($report['users_created'])->toBe(0);
    expect(Note::where('code', 'aq2b3')->sole()->user->email)->toBe('author@example.com');
    expect(Note::where('code', 'abq4x')->sole()->user->email)->toBe('author@example.com');
    expect(Note::withTrashed()->where('code', 'zde77')->sole()->user->email)->toBe('importer@example.com');
});

it('round-trips the golden master byte-exactly through import and export', function () {
    Storage::fake('local');
    Storage::disk('local')->put('imports/export.json', file_get_contents(base_path('tests/fixtures/export-v1.json')));

    (new ImportNotes('local', 'imports/export.json'))->handle();
    $reExported = json_encode(Note::exportPayload(), Note::EXPORT_JSON_FLAGS);

    expect($reExported)->toBe(file_get_contents(base_path('tests/fixtures/export-v1.json')));
});

it('imports live notes findable in search and trashed ones not', function () {
    // Under the test-pinned collection driver (and the shipped database driver)
    // this is really pinning that deleted_at came across - those engines query
    // the live table, so the job's withoutSyncingToSearch guard is unexercisable
    // here. The guard exists for the meilisearch opt-in (devnotes-gbHJd.6.2).
    Storage::fake('local');
    Storage::disk('local')->put('imports/export.json', file_get_contents(base_path('tests/fixtures/export-v1.json')));

    (new ImportNotes('local', 'imports/export.json'))->handle();

    expect(Note::search('teamless')->get()->pluck('code')->all())->toBe(['aq2b3']);
    expect(Note::search('soft-deleted')->get())->toBeEmpty();
});

it('deletes its stored working copy on success and on failure', function () {
    Storage::fake('local');
    Storage::disk('local')->put('imports/good.json', file_get_contents(base_path('tests/fixtures/export-v1.json')));
    Storage::disk('local')->put('imports/bad.json', json_encode([
        'version' => 99,
        'notes' => [['ulid' => '01ARZ3NDEKTSV4RRFFQ69G5FA9', 'code' => 'never', 'title' => 'Should not land', 'body' => 'x']],
    ]));

    (new ImportNotes('local', 'imports/good.json'))->handle();
    expect(fn () => (new ImportNotes('local', 'imports/bad.json'))->handle())
        ->toThrow(RuntimeException::class, 'Unsupported export version');

    Storage::disk('local')->assertMissing('imports/good.json');
    Storage::disk('local')->assertMissing('imports/bad.json');
    expect(Note::withTrashed()->where('code', 'never')->exists())->toBeFalse();
});
