<?php

use App\Livewire\NoteForm;
use App\Models\Note;
use App\Models\User;
use Livewire\Livewire;

it('rejects invalid notes without creating anything', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(NoteForm::class)
        ->call('openCreate')
        ->set('editing.title', '')
        ->set('editing.body', '')
        ->call('save')
        ->assertHasErrors(['editing.title', 'editing.body']);

    Livewire::actingAs($user)
        ->test(NoteForm::class)
        ->call('openCreate')
        ->set('editing.title', str_repeat('x', 256))
        ->set('editing.body', 'A body')
        ->call('save')
        ->assertHasErrors(['editing.title']);

    expect(Note::count())->toBe(0);
});

it('lets any user edit any note without stealing authorship', function () {
    $author = User::factory()->create();
    $editor = User::factory()->create();
    $note = Note::factory()->create(['title' => 'Old titel', 'user_id' => $author->id]);

    Livewire::actingAs($editor)
        ->test(NoteForm::class)
        ->call('openEdit', $note->id)
        ->assertSet('editing.title', 'Old titel')
        ->set('editing.title', 'Old title, typo fixed')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('note-saved');

    $note->refresh();
    expect($note->title)->toBe('Old title, typo fixed');
    expect($note->user->is($author))->toBeTrue();
    expect(Note::count())->toBe(1);
});

it('creates a note owned by the logged-in user', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(NoteForm::class)
        ->call('openCreate')
        ->set('editing.title', 'Flyout focus gotcha')
        ->set('editing.body', 'Set wire:key on the trigger button.')
        ->call('save')
        ->assertHasNoErrors();

    $note = Note::sole();
    expect($note->title)->toBe('Flyout focus gotcha');
    expect($note->user->is($user))->toBeTrue();
});
