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

it('gives a 404 for missing and soft-deleted notes', function () {
    $user = User::factory()->create();
    $deletedNote = Note::factory()->create();
    $deletedNote->delete();

    $this->actingAs($user)->get(route('notes.show', 999))->assertNotFound();
    $this->actingAs($user)->get(route('notes.show', $deletedNote->id))->assertNotFound();
});

it('redirects guests to the login page', function () {
    $note = Note::factory()->create();

    $this->get(route('notes.show', $note))->assertRedirect(route('login'));
});

it('shows a note with its rendered markdown, author and id', function () {
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
    $response->assertSee("#{$note->id}");
});
