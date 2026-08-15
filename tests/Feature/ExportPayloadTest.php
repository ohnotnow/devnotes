<?php

use App\Models\Note;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;

it('builds a versioned export payload matching the golden master', function () {
    Carbon::setTestNow('2026-08-15 10:00:00');
    $author = User::factory()->create([
        'email' => 'author@example.com',
        'forenames' => 'Test',
        'surname' => 'Author',
        'is_admin' => false,
        'is_staff' => true,
    ]);
    $adminAuthor = User::factory()->admin()->create([
        'email' => 'admin@example.com',
        'forenames' => 'Admin',
        'surname' => 'Author',
        'is_staff' => true,
    ]);
    $developers = Team::factory()->create(['name' => 'developers']);
    $sysadmins = Team::factory()->create(['name' => 'sysadmins']);
    Note::factory()->create([
        'id' => 1,
        'title' => 'A teamless gotcha',
        'body' => "Runs in the whole pot.\n\nSee the [docs](https://example.com/docs) for more.",
        'user_id' => $author->id,
    ]);
    $teamNote = Note::factory()->create([
        'id' => 4,
        'title' => 'How to install the puppet client on Rocky Linux',
        'body' => "## Install\n\nAdd the puppet repo, then `dnf install puppet-agent`.",
        'user_id' => $author->id,
    ]);
    $teamNote->teams()->attach([$sysadmins->id, $developers->id]);
    $deletedNote = Note::factory()->create([
        'id' => 7,
        'title' => 'A deleted note',
        'body' => 'This one was soft-deleted.',
        'user_id' => $adminAuthor->id,
    ]);
    $deletedNote->delete();

    $json = json_encode(Note::exportPayload(), Note::EXPORT_JSON_FLAGS);

    // Byte-exact contract: the fixture is stored WITHOUT a trailing newline -
    // an editor-on-save that adds one breaks this comparison, not the app.
    expect($json)->toBe(file_get_contents(base_path('tests/fixtures/export-v1.json')));
});
