<?php

use App\Livewire\NotesIndex;
use App\Models\Channel;
use App\Models\Note;
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

it('matches every search word as a partial, in any order', function () {
    $user = User::factory()->create();
    Note::factory()->create(['title' => 'Updating pivot tables', 'body' => 'Use the pivot methods on the relationship.']);
    Note::factory()->create(['title' => 'Unrelated tip', 'body' => 'Nothing about that topic here.']);

    Livewire::actingAs($user)
        ->test(NotesIndex::class)
        ->set('search', 'pivot updat')
        ->assertSee('Updating pivot tables')
        ->assertDontSee('Unrelated tip');
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

it('starts the channel filter on the viewer\'s subscribed channels and searches everything when cleared', function () {
    $developers = Channel::factory()->create(['name' => 'developers']);
    $sysadmins = Channel::factory()->create(['name' => 'sysadmins']);
    $developer = User::factory()->create();
    $developer->channels()->attach($developers);
    $developerNote = Note::factory()->create(['title' => 'Docker layer cache misses']);
    $developerNote->channels()->attach($developers);
    $sysadminNote = Note::factory()->create(['title' => 'Docker daemon log rotation']);
    $sysadminNote->channels()->attach($sysadmins);

    Livewire::actingAs($developer)
        ->test(NotesIndex::class)
        ->assertSet('channels', [$developers->id])
        ->set('search', 'docker')
        ->assertSee('Docker layer cache misses')
        ->assertDontSee('Docker daemon log rotation')
        ->set('channels', [])
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

it('orders the browse list by most recently updated, matching search results', function () {
    $user = User::factory()->create();
    Note::factory()->create(['title' => 'Middle gotcha', 'created_at' => now()->subDays(3), 'updated_at' => now()->subDay()]);
    Note::factory()->create(['title' => 'Newest gotcha', 'created_at' => now()->subDays(2), 'updated_at' => now()]);
    Note::factory()->create(['title' => 'Oldest gotcha', 'created_at' => now(), 'updated_at' => now()->subDays(2)]);

    Livewire::actingAs($user)
        ->test(NotesIndex::class)
        ->assertSeeInOrder(['Newest gotcha', 'Middle gotcha', 'Oldest gotcha']);
});

it('shows channel badges when browsing, when searching and when showing all channels', function () {
    $distinctChannel = Channel::factory()->create(['name' => 'platform-squad']);
    $viewer = User::factory()->create();
    $viewer->channels()->attach($distinctChannel);
    $author = User::factory()->create(['forenames' => 'Badged', 'surname' => 'Author']);
    $taggedNote = Note::factory()->create(['title' => 'Docker layer cache misses', 'user_id' => $author->id]);
    $taggedNote->channels()->attach($distinctChannel);

    // assertSeeInOrder because a bare assertSee would match the note form's
    // channel checkboxes: the badge renders before the author cell in the row,
    // while the form only appears after the table.
    Livewire::actingAs($viewer)
        ->test(NotesIndex::class)
        ->assertSeeInOrder(['platform-squad', 'Badged Author'])
        ->set('search', 'docker')
        ->assertSeeInOrder(['platform-squad', 'Badged Author'])
        ->set('search', '')
        ->set('channels', [])
        ->assertSeeInOrder(['platform-squad', 'Badged Author']);
});

it('scopes browsing to the filtered channels while whole-pot notes always show', function () {
    $developers = Channel::factory()->create(['name' => 'developers']);
    $sysadmins = Channel::factory()->create(['name' => 'sysadmins']);
    $developer = User::factory()->create();
    $developer->channels()->attach($developers);
    $developerNote = Note::factory()->create(['title' => 'Docker layer cache misses']);
    $developerNote->channels()->attach($developers);
    Note::factory()->create(['title' => 'Compose healthcheck gotcha']);
    $sysadminNote = Note::factory()->create(['title' => 'Docker daemon log rotation']);
    $sysadminNote->channels()->attach($sysadmins);

    Livewire::actingAs($developer)
        ->test(NotesIndex::class)
        ->assertSee('Docker layer cache misses')
        ->assertSee('Compose healthcheck gotcha')
        ->assertDontSee('Docker daemon log rotation')
        ->set('channels', [$sysadmins->id])
        ->assertSee('Docker daemon log rotation')
        ->assertSee('Compose healthcheck gotcha')
        ->assertDontSee('Docker layer cache misses');
});

it('filters to a single channel when its badge is clicked', function () {
    $developers = Channel::factory()->create(['name' => 'developers']);
    $sysadmins = Channel::factory()->create(['name' => 'sysadmins']);
    $viewer = User::factory()->create();
    $viewer->channels()->attach([$developers->id, $sysadmins->id]);
    $developerNote = Note::factory()->create(['title' => 'Docker layer cache misses']);
    $developerNote->channels()->attach($developers);
    $sysadminNote = Note::factory()->create(['title' => 'Docker daemon log rotation']);
    $sysadminNote->channels()->attach($sysadmins);

    Livewire::actingAs($viewer)
        ->test(NotesIndex::class)
        ->assertSee('Docker layer cache misses')
        ->assertSee('Docker daemon log rotation')
        ->call('filterChannel', $sysadmins->id)
        ->assertSet('channels', [$sysadmins->id])
        ->assertSee('Docker daemon log rotation')
        ->assertDontSee('Docker layer cache misses');
});

it('honours a channel filter arriving as url strings, overriding the subscribed default', function () {
    $developers = Channel::factory()->create(['name' => 'developers']);
    $sysadmins = Channel::factory()->create(['name' => 'sysadmins']);
    $developer = User::factory()->create();
    $developer->channels()->attach($developers);
    $developerNote = Note::factory()->create(['title' => 'Docker layer cache misses']);
    $developerNote->channels()->attach($developers);
    $sysadminNote = Note::factory()->create(['title' => 'Docker daemon log rotation']);
    $sysadminNote->channels()->attach($sysadmins);

    // Url-tracked properties arrive as strings - the filter must cast, not trust.
    Livewire::actingAs($developer)
        ->withQueryParams(['search' => 'docker', 'channels' => [(string) $sysadmins->id]])
        ->test(NotesIndex::class)
        ->assertSee('Docker daemon log rotation')
        ->assertDontSee('Docker layer cache misses');
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

it('shows an empty state when a search matches nothing', function () {
    $user = User::factory()->create();
    Note::factory()->create(['title' => 'Postgres ilike surprise']);

    Livewire::actingAs($user)
        ->test(NotesIndex::class)
        ->set('search', 'zzzznotfound')
        ->assertSee('No notes match your search')
        ->assertDontSee('Postgres ilike surprise');
});

it('announces the result count to screen readers when filtering', function () {
    $user = User::factory()->create();
    Note::factory()->create(['title' => 'Postgres ilike surprise']);
    Note::factory()->create(['title' => 'Postgres sequence reset']);

    Livewire::actingAs($user)
        ->test(NotesIndex::class)
        ->set('search', 'postgres')
        ->assertSeeHtml('role="status"')
        ->assertSee('2 notes found');
});

it('returns to the first page when the search or the channel scope changes', function () {
    $reader = User::factory()->create();
    Note::factory()->count(25)->create();
    $matchOnFirstPage = Note::factory()->create(['title' => 'Docker layer cache misses', 'updated_at' => now()->addMinute()]);

    Livewire::actingAs($reader)
        ->test(NotesIndex::class)
        ->call('setPage', 2)
        ->assertDontSee('Docker layer cache misses')
        ->set('search', 'docker')
        ->assertSee('Docker layer cache misses');

    Livewire::actingAs($reader)
        ->test(NotesIndex::class)
        ->call('setPage', 2)
        ->assertDontSee($matchOnFirstPage->title)
        ->set('channels', [])
        ->assertSee($matchOnFirstPage->title);
});
