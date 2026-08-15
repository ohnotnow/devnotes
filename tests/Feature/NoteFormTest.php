<?php

use App\Livewire\NoteForm;
use App\Models\Note;
use App\Models\Team;
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

it('pre-checks the author\'s default teams on create and saves the selection', function () {
    $developers = Team::factory()->create(['name' => 'developers']);
    $sysadmins = Team::factory()->create(['name' => 'sysadmins']);
    $author = User::factory()->create();
    $author->teams()->attach($developers);

    Livewire::actingAs($author)
        ->test(NoteForm::class)
        ->call('openCreate')
        ->assertSet('selectedTeamIds', [$developers->id])
        ->set('editing.title', 'A note for the sysadmins too')
        ->set('editing.body', 'Body.')
        ->set('selectedTeamIds', [$developers->id, $sysadmins->id])
        ->call('save')
        ->assertHasNoErrors();

    expect(Note::sole()->teams()->pluck('teams.id')->all())->toEqualCanonicalizing([$developers->id, $sysadmins->id]);
});

it('reflects and syncs a note\'s teams on edit', function () {
    $developers = Team::factory()->create(['name' => 'developers']);
    $sysadmins = Team::factory()->create(['name' => 'sysadmins']);
    $editor = User::factory()->create();
    $note = Note::factory()->create();
    $note->teams()->attach($developers);

    Livewire::actingAs($editor)
        ->test(NoteForm::class)
        ->call('openEdit', $note->id)
        ->assertSet('selectedTeamIds', [$developers->id])
        ->set('selectedTeamIds', [$sysadmins->id])
        ->call('save')
        ->assertHasNoErrors();

    expect($note->teams()->pluck('teams.id')->all())->toBe([$sysadmins->id]);
});

it('saves a deliberately teamless note when every box is unchecked', function () {
    $developers = Team::factory()->create(['name' => 'developers']);
    $author = User::factory()->create();
    $author->teams()->attach($developers);

    Livewire::actingAs($author)
        ->test(NoteForm::class)
        ->call('openCreate')
        ->set('editing.title', 'Whole-pot note')
        ->set('editing.body', 'Body.')
        ->set('selectedTeamIds', [])
        ->call('save')
        ->assertHasNoErrors();

    expect(Note::sole()->teams()->count())->toBe(0);
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
