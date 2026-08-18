<?php

use App\Enums\ActivityAction;
use App\Jobs\ImportNotes;
use App\Models\Activity;
use App\Models\Note;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

it('records an activity when a user creates a note', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $note = Note::factory()->create(['code' => 'abq4x', 'title' => 'Flyout modals lose focus']);

    $activity = Activity::sole();
    expect($activity->user->is($user))->toBeTrue();
    expect($activity->note->is($note))->toBeTrue();
    expect($activity->action)->toBe(ActivityAction::Created);
    expect($activity->description)->toBe("created note #abq4x 'Flyout modals lose focus'");
    expect(Str::isUlid($activity->ulid))->toBeTrue();
});

it('records an activity when a user edits a note', function () {
    $author = User::factory()->create();
    $editor = User::factory()->create();
    $note = Note::factory()->create(['user_id' => $author->id, 'code' => 'abq4x']);
    $this->actingAs($editor);

    $note->update(['title' => 'Sharper title']);

    $activity = Activity::sole();
    expect($activity->user->is($editor))->toBeTrue();
    expect($activity->action)->toBe(ActivityAction::Edited);
    expect($activity->description)->toBe("edited note #abq4x 'Sharper title'");
});

it('records a single activity when a user deletes a note', function () {
    $note = Note::factory()->create(['code' => 'abq4x', 'title' => 'Stale advice']);
    $this->actingAs(User::factory()->create());

    $note->delete();

    $activity = Activity::sole();
    expect($activity->action)->toBe(ActivityAction::Deleted);
    expect($activity->description)->toBe("deleted note #abq4x 'Stale advice'");
});

it('records a single activity when a user restores a note', function () {
    $note = Note::factory()->create(['code' => 'abq4x', 'title' => 'Not stale after all']);
    $note->delete();
    $this->actingAs(User::factory()->create());

    $note->restore();

    $activity = Activity::sole();
    expect($activity->action)->toBe(ActivityAction::Restored);
    expect($activity->description)->toBe("restored note #abq4x 'Not stale after all'");
});

it('records an activity when a user reads a note', function () {
    $note = Note::factory()->create(['code' => 'abq4x', 'title' => 'Worth a read']);
    $reader = User::factory()->create();

    $this->actingAs($reader)->get(route('notes.show', $note))->assertOk();

    $activity = Activity::sole();
    expect($activity->user->is($reader))->toBeTrue();
    expect($activity->action)->toBe(ActivityAction::Read);
    expect($activity->description)->toBe("read note #abq4x 'Worth a read'");
});

it('records nothing when nobody is signed in, as in seeders and console commands', function () {
    $note = Note::factory()->create();
    $note->update(['title' => 'Changed by the system']);
    $note->incrementReadCount();

    expect(Activity::count())->toBe(0);
});

it('logs nothing of its own during an import, even when an admin runs it in-process', function () {
    Storage::fake('local');
    Storage::disk('local')->put('imports/export.json', file_get_contents(base_path('tests/fixtures/export-v1.json')));
    $importingAdmin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($importingAdmin);

    (new ImportNotes('local', 'imports/export.json'))->handle();

    // The only activity rows are the two the file itself carries - the
    // importing admin is not written into history as the notes' creator.
    expect(Note::withTrashed()->count())->toBe(3);
    expect(Activity::count())->toBe(2);
    expect(Activity::where('user_id', $importingAdmin->id)->count())->toBe(0);
});
