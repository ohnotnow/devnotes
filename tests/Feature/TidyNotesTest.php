<?php

use App\Livewire\TidyNotes;
use App\Models\Note;
use App\Models\User;
use Livewire\Livewire;

it('lists only the signed-in user\'s notes, least read first', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    // Inserted in reverse of the expected display order so insertion/id order
    // cannot fake a pass; the matching read_counts force the last_read_at
    // tie-break to do real work (never on a NULL - the 0-count note stands alone).
    Note::factory()->create([
        'user_id' => $user->id,
        'title' => 'Read recently gotcha',
        'read_count' => 2,
        'last_read_at' => now(),
    ]);
    Note::factory()->create([
        'user_id' => $user->id,
        'title' => 'Read long ago gotcha',
        'read_count' => 2,
        'last_read_at' => now()->subMonth(),
    ]);
    Note::factory()->create(['user_id' => $user->id, 'title' => 'Never read gotcha']);
    Note::factory()->create(['user_id' => $otherUser->id, 'title' => 'Someone elses gotcha']);

    $response = $this->actingAs($user)->get(route('tidy'));

    $response->assertSuccessful();
    $response->assertSeeInOrder(['Never read gotcha', 'Read long ago gotcha', 'Read recently gotcha']);
    $response->assertDontSee('Someone elses gotcha');
});

it('shows every note with authors to an admin who turns on show-all', function () {
    $admin = User::factory()->admin()->create();
    $otherUser = User::factory()->create();
    Note::factory()->create(['user_id' => $otherUser->id, 'title' => 'Someone elses gotcha']);

    Livewire::actingAs($admin)
        ->test(TidyNotes::class)
        ->assertDontSee('Someone elses gotcha')
        ->set('showAll', true)
        ->assertSee('Someone elses gotcha')
        ->assertSee($otherUser->full_name);
});

it('never shows other peoples notes or the show-all switch to a regular user', function () {
    $regularUser = User::factory()->create();
    $otherUser = User::factory()->create();
    Note::factory()->create(['user_id' => $otherUser->id, 'title' => 'Someone elses gotcha']);
    Note::factory()->create(['user_id' => $regularUser->id, 'title' => 'My own gotcha']);

    Livewire::actingAs($regularUser)
        ->test(TidyNotes::class)
        ->assertDontSee('Show all notes')
        ->set('showAll', true)
        ->assertSee('My own gotcha')
        ->assertDontSee('Someone elses gotcha');
});

it('offers the preview from both the code badge and the title', function () {
    $user = User::factory()->create();
    $note = Note::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get(route('tidy'));

    expect(substr_count($response->getContent(), 'wire:click="preview('.$note->id.')"'))->toBe(2);
});

it('previews a note in the flyout without bumping its read tracking', function () {
    // The flyout is triage, not a deliberate read - bumping would corrupt the
    // very heuristic the screen displays.
    $user = User::factory()->create();
    $note = Note::factory()->create([
        'user_id' => $user->id,
        'title' => 'Preview gotcha',
        'body' => 'Some **bold** advice.',
        'read_count' => 3,
    ]);

    Livewire::actingAs($user)
        ->test(TidyNotes::class)
        ->call('preview', $note->id)
        ->assertSee('Preview gotcha')
        ->assertSee('<strong>bold</strong>', escape: false);

    expect($note->refresh()->read_count)->toBe(3);
});

it('links to the tidy page from the sidebar', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/')->assertSee(route('tidy'));
});

it('soft-deletes exactly the targeted note from the tidy screen', function () {
    $user = User::factory()->create();
    $noteToDelete = Note::factory()->create(['user_id' => $user->id]);
    $noteToKeep = Note::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(TidyNotes::class)
        ->call('delete', $noteToDelete->id);

    expect(Note::find($noteToDelete->id))->toBeNull();
    expect(Note::withTrashed()->find($noteToDelete->id))->not->toBeNull();
    expect(Note::find($noteToKeep->id))->not->toBeNull();
});

it('returns an admin to the first page when show-all is switched on', function () {
    $admin = User::factory()->admin()->create();
    $otherUser = User::factory()->create();
    Note::factory()->count(25)->create(['user_id' => $admin->id, 'read_count' => 5]);
    Note::factory()->create(['user_id' => $otherUser->id, 'title' => 'Least read of the lot', 'read_count' => 0]);

    Livewire::actingAs($admin)
        ->test(TidyNotes::class)
        ->call('setPage', 2)
        ->assertDontSee('Least read of the lot')
        ->set('showAll', true)
        ->assertSee('Least read of the lot');
});
