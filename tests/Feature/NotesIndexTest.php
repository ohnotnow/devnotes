<?php

use App\Livewire\NotesIndex;
use App\Models\Note;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

it('filters notes by search term across title and body', function () {
    $user = User::factory()->create();
    Note::factory()->create(['title' => 'Livewire modal focus gotcha', 'body' => 'Set wire:key on the trigger.']);
    Note::factory()->create(['title' => 'Postgres ilike surprise', 'body' => 'LIKE is case sensitive there.']);
    Note::factory()->create(['title' => 'Sequences after restore', 'body' => 'Another postgres one - reset sequences after a pg_restore.']);

    Livewire::actingAs($user)
        ->test(NotesIndex::class)
        ->set('search', 'postgres')
        ->assertSee('Postgres ilike surprise')
        ->assertSee('Sequences after restore')
        ->assertDontSee('Livewire modal focus gotcha');
});

it('keeps trashed notes out of the index and search results', function () {
    $user = User::factory()->create();
    Note::factory()->create(['title' => 'A live postgres gotcha']);
    $trashedNote = Note::factory()->create(['title' => 'A binned postgres gotcha']);
    $trashedNote->delete();

    Livewire::actingAs($user)
        ->test(NotesIndex::class)
        ->assertSee('A live postgres gotcha')
        ->assertDontSee('A binned postgres gotcha')
        ->set('search', 'postgres')
        ->assertSee('A live postgres gotcha')
        ->assertDontSee('A binned postgres gotcha');
});

it('scopes search to the viewer\'s teams with a toggle to search all teams', function () {
    $developers = Team::factory()->create(['name' => 'developers']);
    $sysadmins = Team::factory()->create(['name' => 'sysadmins']);
    $developer = User::factory()->create();
    $developer->teams()->attach($developers);
    $developerNote = Note::factory()->create(['title' => 'Docker layer cache misses']);
    $developerNote->teams()->attach($developers);
    $sysadminNote = Note::factory()->create(['title' => 'Docker daemon log rotation']);
    $sysadminNote->teams()->attach($sysadmins);

    Livewire::actingAs($developer)
        ->test(NotesIndex::class)
        ->set('search', 'docker')
        ->assertSee('Docker layer cache misses')
        ->assertDontSee('Docker daemon log rotation')
        ->set('broader', true)
        ->assertSee('Docker layer cache misses')
        ->assertSee('Docker daemon log rotation');
});

it('orders search results by most recently updated', function () {
    $user = User::factory()->create();
    Note::factory()->create(['title' => 'Postgres middle gotcha', 'updated_at' => now()->subDay()]);
    Note::factory()->create(['title' => 'Postgres newest gotcha', 'updated_at' => now()]);
    Note::factory()->create(['title' => 'Postgres oldest gotcha', 'updated_at' => now()->subDays(2)]);

    Livewire::actingAs($user)
        ->test(NotesIndex::class)
        ->set('search', 'postgres')
        ->assertSeeInOrder(['Postgres newest gotcha', 'Postgres middle gotcha', 'Postgres oldest gotcha']);
});

it('shows team badges on search results', function () {
    $distinctTeam = Team::factory()->create(['name' => 'platform-squad']);
    $viewer = User::factory()->create();
    $viewer->teams()->attach($distinctTeam);
    $taggedNote = Note::factory()->create(['title' => 'Docker layer cache misses']);
    $taggedNote->teams()->attach($distinctTeam);

    // No assertDontSee for the browse state: the note form's team checkboxes
    // legitimately put every team name in the page markup.
    Livewire::actingAs($viewer)
        ->test(NotesIndex::class)
        ->set('search', 'docker')
        ->assertSee('platform-squad');
});

it('shows all notes when browsing without a search term', function () {
    $developers = Team::factory()->create(['name' => 'developers']);
    $sysadmins = Team::factory()->create(['name' => 'sysadmins']);
    $developer = User::factory()->create();
    $developer->teams()->attach($developers);
    $sysadminNote = Note::factory()->create(['title' => 'Docker daemon log rotation']);
    $sysadminNote->teams()->attach($sysadmins);

    Livewire::actingAs($developer)
        ->test(NotesIndex::class)
        ->assertSee('Docker daemon log rotation');
});

it('honours a broader flag arriving as a url string', function () {
    $developers = Team::factory()->create(['name' => 'developers']);
    $sysadmins = Team::factory()->create(['name' => 'sysadmins']);
    $developer = User::factory()->create();
    $developer->teams()->attach($developers);
    $sysadminNote = Note::factory()->create(['title' => 'Docker daemon log rotation']);
    $sysadminNote->teams()->attach($sysadmins);

    Livewire::actingAs($developer)
        ->withQueryParams(['search' => 'docker', 'broader' => '1'])
        ->test(NotesIndex::class)
        ->assertSee('Docker daemon log rotation');

    Livewire::actingAs($developer)
        ->withQueryParams(['search' => 'docker', 'broader' => '0'])
        ->test(NotesIndex::class)
        ->assertDontSee('Docker daemon log rotation');
});

it('tracks the search term in the url so searches are shareable', function () {
    $user = User::factory()->create();
    Note::factory()->create(['title' => 'Postgres ilike surprise']);

    Livewire::actingAs($user)
        ->withQueryParams(['search' => 'postgres'])
        ->test(NotesIndex::class)
        ->assertSet('search', 'postgres')
        ->assertSee('Postgres ilike surprise');
});

it('lists notes newest-first with their code, title and author', function () {
    $viewer = User::factory()->create(['forenames' => 'Vera', 'surname' => 'Viewer']);
    $olderAuthor = User::factory()->create(['forenames' => 'Older', 'surname' => 'Author']);
    $newerAuthor = User::factory()->create(['forenames' => 'Newer', 'surname' => 'Author']);
    $olderNote = Note::factory()->create(['title' => 'An older gotcha', 'user_id' => $olderAuthor->id, 'created_at' => now()->subDay()]);
    $trashedNote = Note::factory()->create(['title' => 'A binned gotcha']);
    $trashedNote->delete();
    Note::factory()->create(['title' => 'A newer gotcha', 'user_id' => $newerAuthor->id]);

    $response = $this->actingAs($viewer)->get('/');

    $response->assertSuccessful();
    $response->assertSeeInOrder(['A newer gotcha', 'Newer Author', 'An older gotcha', 'Older Author']);
    $response->assertSee(route('notes.show', $olderNote));
    $response->assertSee("#{$olderNote->code}");
    $response->assertDontSee('A binned gotcha');
});
