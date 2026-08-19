<?php

use App\Livewire\NoteShow;
use App\Models\Note;
use App\Models\User;
use Livewire\Livewire;

it('soft-deletes just this note and returns to the index', function () {
    $user = User::factory()->create();
    $noteToDelete = Note::factory()->create();
    $noteToKeep = Note::factory()->create();

    Livewire::actingAs($user)
        ->test(NoteShow::class, ['note' => $noteToDelete])
        ->call('delete')
        ->assertRedirect(route('home'));

    expect(Note::find($noteToDelete->id))->toBeNull();
    expect(Note::withTrashed()->find($noteToDelete->id))->not->toBeNull();
    expect(Note::find($noteToKeep->id))->not->toBeNull();
});

it('gives a 404 for unknown note codes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('notes.show', 'zzzzz'))->assertNotFound();
});

it('shows a soft-deleted note with a deletion callout instead of a 404', function () {
    $user = User::factory()->create();
    $note = Note::factory()->create(['title' => 'A binned gotcha']);
    $note->delete();

    $response = $this->actingAs($user)->get(route('notes.show', $note->code));

    $response->assertSuccessful();
    $response->assertSee('A binned gotcha');
    $response->assertSee('This note was deleted on the '.$note->deleted_at->format('jS \o\f F, Y'));
});

it('offers only a restore button on a trashed note page', function () {
    $user = User::factory()->create();
    $liveNote = Note::factory()->create();
    $trashedNote = Note::factory()->create();
    $trashedNote->delete();

    $trashedPage = $this->actingAs($user)->get(route('notes.show', $trashedNote->code));
    $trashedPage->assertSee('Restore');
    $trashedPage->assertDontSee('Edit');
    $trashedPage->assertDontSee('Delete');

    $livePage = $this->actingAs($user)->get(route('notes.show', $liveNote->code));
    $livePage->assertSee('Edit');
    $livePage->assertSee('Delete');
    $livePage->assertDontSee('Restore');
});

it('restores exactly the trashed note and it reappears in search', function () {
    $user = User::factory()->create();
    $noteToRestore = Note::factory()->create(['title' => 'Restorable gotcha']);
    $noteToStayTrashed = Note::factory()->create();
    $noteToRestore->delete();
    $noteToStayTrashed->delete();
    expect(Note::searchScoped($user, 'Restorable gotcha')->get())->toBeEmpty();

    Livewire::actingAs($user)
        ->test(NoteShow::class, ['note' => Note::withTrashed()->find($noteToRestore->id)])
        ->call('restore');

    expect(Note::find($noteToRestore->id)->deleted_at)->toBeNull();
    expect(Note::withTrashed()->find($noteToStayTrashed->id)->trashed())->toBeTrue();
    expect(Note::searchScoped($user, 'Restorable gotcha')->get()->pluck('id')->all())->toContain($noteToRestore->id);
});

it('describes deletion as removal from discovery, not dangling references', function () {
    $user = User::factory()->create();
    $note = Note::factory()->create();

    $response = $this->actingAs($user)->get(route('notes.show', $note));

    // Truncated before "agents' digests" - the apostrophe is HTML-escaped in
    // the rendered page, so completing the sentence here would go red.
    $response->assertSee('disappears from search, the notes list, and agents');
});

it('resolves the note page by code, not by internal id', function () {
    $user = User::factory()->create();
    $note = Note::factory()->create(['code' => 'abq4x', 'title' => 'Found by code']);

    $this->actingAs($user)->get('/notes/abq4x')->assertSuccessful()->assertSee('Found by code');
    $this->actingAs($user)->get("/notes/{$note->id}")->assertNotFound();
});

it('bumps read tracking when the note page is visited', function () {
    $user = User::factory()->create();
    $note = Note::factory()->create();

    $this->actingAs($user)->get(route('notes.show', $note))->assertSuccessful();

    $note->refresh();
    expect($note->read_count)->toBe(1);
    expect($note->last_read_at)->not->toBeNull();
});

it('bumps read tracking once on mount, not on livewire round-trips', function () {
    // If the bump drifted from mount() into render(), every round-trip on the
    // page would inflate the counter.
    $user = User::factory()->create();
    $note = Note::factory()->create();

    Livewire::actingAs($user)
        ->test(NoteShow::class, ['note' => $note])
        ->call('refreshNote');

    expect($note->refresh()->read_count)->toBe(1);
});

it('redirects guests to the login page', function () {
    $note = Note::factory()->create();

    $this->get(route('notes.show', $note))->assertRedirect(route('login'));
});

it('shows a note with its rendered markdown, author and code', function () {
    $viewer = User::factory()->create(['forenames' => 'Vera', 'surname' => 'Viewer']);
    $author = User::factory()->create(['forenames' => 'Test', 'surname' => 'Author']);
    $note = Note::factory()->create([
        'title' => 'A markdown gotcha',
        'body' => 'Some **bold** advice.',
        'user_id' => $author->id,
    ]);

    $response = $this->actingAs($viewer)->get(route('notes.show', $note));

    $response->assertSuccessful();
    $response->assertSee('A markdown gotcha');
    $response->assertSee('<strong>bold</strong>', escape: false);
    $response->assertSee('Test Author');
    $response->assertSee("#{$note->code}");
});
