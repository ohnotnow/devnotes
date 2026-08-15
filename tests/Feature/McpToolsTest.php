<?php

use App\Mcp\Servers\DevnotesServer;
use App\Mcp\Tools\AddNote;
use App\Mcp\Tools\GetNote;
use App\Mcp\Tools\SearchNotes;
use App\Models\Note;
use App\Models\OAuthUser;
use App\Models\Team;
use App\Models\User;

it('finds matching notes via search-notes returning snippets rather than full bodies', function () {
    $user = User::factory()->create();
    // Marker sits at ~char 217 so it pins the 200-char snippet limit specifically.
    $longBody = str_repeat('Useful words about the gotcha. ', 7).'DEEP-TAIL-MARKER';
    $matchingNote = Note::factory()->create(['title' => 'Postgres like-vs-ilike gotcha', 'body' => $longBody]);
    Note::factory()->create(['title' => 'Unrelated flux thing', 'body' => 'Nothing relevant here.']);

    $response = DevnotesServer::actingAs(OAuthUser::findOrFail($user->id))->tool(SearchNotes::class, [
        'query' => 'postgres',
    ]);

    $response->assertOk();
    $response->assertName('search-notes');
    $response->assertSee('"id":'.$matchingNote->id);
    $response->assertSee('Postgres like-vs-ilike gotcha');
    $response->assertSee('Useful words about the gotcha.');
    $response->assertDontSee('DEEP-TAIL-MARKER');
    $response->assertDontSee('Unrelated flux thing');

    $noMatches = DevnotesServer::actingAs(OAuthUser::findOrFail($user->id))->tool(SearchNotes::class, [
        'query' => 'nothing-matches-this',
    ]);

    $noMatches->assertOk();
    $noMatches->assertSee('"results":[]');
    // This caller subscribes to no teams, so the search was never scoped and
    // there is nothing to widen - no hint.
    $noMatches->assertDontSee('retry with broader');
});

it('keeps the hint on a scoped search with zero results', function () {
    $developers = Team::factory()->create(['name' => 'developers']);
    $agentUser = User::factory()->create();
    $agentUser->teams()->attach($developers);

    $response = DevnotesServer::actingAs(OAuthUser::findOrFail($agentUser->id))->tool(SearchNotes::class, [
        'query' => 'nothing-matches-this',
    ]);

    $response->assertOk();
    $response->assertSee('"results":[]');
    $response->assertSee('retry with broader');
});

it('scopes search-notes to the caller\'s teams and hints that broader exists', function () {
    $developers = Team::factory()->create(['name' => 'developers']);
    $sysadmins = Team::factory()->create(['name' => 'sysadmins']);
    $agentUser = User::factory()->create();
    $agentUser->teams()->attach($developers);
    $developerNote = Note::factory()->create(['title' => 'Docker layer cache misses']);
    $developerNote->teams()->attach($developers);
    $sysadminNote = Note::factory()->create(['title' => 'Docker daemon log rotation']);
    $sysadminNote->teams()->attach($sysadmins);

    $response = DevnotesServer::actingAs(OAuthUser::findOrFail($agentUser->id))->tool(SearchNotes::class, [
        'query' => 'docker',
    ]);

    $response->assertOk();
    $response->assertSee('Docker layer cache misses');
    $response->assertDontSee('Docker daemon log rotation');
    $response->assertSee('retry with broader');
});

it('labels out-of-team hits under broader and drops the hint', function () {
    $developers = Team::factory()->create(['name' => 'developers']);
    $sysadmins = Team::factory()->create(['name' => 'sysadmins']);
    $agentUser = User::factory()->create();
    $agentUser->teams()->attach($developers);
    $developerNote = Note::factory()->create(['title' => 'Docker layer cache misses']);
    $developerNote->teams()->attach($developers);
    $sysadminNote = Note::factory()->create(['title' => 'Docker daemon log rotation']);
    $sysadminNote->teams()->attach($sysadmins);
    Note::factory()->create(['title' => 'Docker compose healthcheck gotcha']);

    $response = DevnotesServer::actingAs(OAuthUser::findOrFail($agentUser->id))->tool(SearchNotes::class, [
        'query' => 'docker',
        'broader' => true,
    ]);

    $response->assertOk();
    $response->assertSee('Docker daemon log rotation');
    $response->assertDontSee('retry with broader');

    // Row keys serialise in build order, so a teams fragment followed by } or
    // by the label pins which rows carry from_outside_your_teams.
    $response->assertSee('"teams":["sysadmins"],"from_outside_your_teams":true');
    $response->assertSee('"teams":["developers"]}');
    $response->assertSee('"teams":[]}');
});

it('returns the full note body via get-note accepting both bare and hash-prefixed ids', function () {
    $user = User::factory()->create();
    $author = User::factory()->create(['forenames' => 'Test', 'surname' => 'Author']);
    $note = Note::factory()->create([
        'title' => 'The soft-delete FK trap',
        'body' => "Some **markdown** advice.\n\nSee #12 too.",
        'user_id' => $author->id,
    ]);

    $bareForm = DevnotesServer::actingAs(OAuthUser::findOrFail($user->id))->tool(GetNote::class, [
        'id' => (string) $note->id,
    ]);

    $bareForm->assertOk();
    $bareForm->assertName('get-note');
    $bareForm->assertSee('The soft-delete FK trap');
    $bareForm->assertSee('Some **markdown** advice.');
    $bareForm->assertSee('Test Author');

    $hashForm = DevnotesServer::actingAs(OAuthUser::findOrFail($user->id))->tool(GetNote::class, [
        'id' => "#{$note->id}",
    ]);

    $hashForm->assertOk();
    $hashForm->assertSee('"id":'.$note->id);
    $hashForm->assertSee('The soft-delete FK trap');
});

it('returns a graceful tool error from get-note for unknown or junk ids', function () {
    $user = User::factory()->create();

    $unknownId = DevnotesServer::actingAs(OAuthUser::findOrFail($user->id))->tool(GetNote::class, [
        'id' => '#999',
    ]);

    $unknownId->assertHasErrors(['No note found with id #999']);

    $junkId = DevnotesServer::actingAs(OAuthUser::findOrFail($user->id))->tool(GetNote::class, [
        'id' => '#note-twelve',
    ]);

    $junkId->assertHasErrors(['No note found with id #note-twelve']);
});

it('rejects invalid add-note input as a tool error and creates nothing', function () {
    $user = User::factory()->create();

    $response = DevnotesServer::actingAs(OAuthUser::findOrFail($user->id))->tool(AddNote::class, [
        'title' => str_repeat('x', 256),
        'body' => '',
    ]);

    $response->assertHasErrors(['title', 'body']);
    expect(Note::count())->toBe(0);
});

it('attaches teams on add-note from names, defaults, or a deliberate empty list', function () {
    $developers = Team::factory()->create(['name' => 'developers']);
    $sysadmins = Team::factory()->create(['name' => 'sysadmins']);
    $agentUser = User::factory()->create();
    $agentUser->teams()->attach($developers);

    $defaulted = DevnotesServer::actingAs(OAuthUser::findOrFail($agentUser->id))->tool(AddNote::class, [
        'title' => 'Defaulted note', 'body' => 'Body.',
    ]);
    $defaulted->assertOk();
    expect(Note::where('title', 'Defaulted note')->sole()->teams()->pluck('teams.id')->all())->toBe([$developers->id]);

    $explicit = DevnotesServer::actingAs(OAuthUser::findOrFail($agentUser->id))->tool(AddNote::class, [
        'title' => 'Explicit note', 'body' => 'Body.', 'teams' => ['sysadmins'],
    ]);
    $explicit->assertOk();
    expect(Note::where('title', 'Explicit note')->sole()->teams()->pluck('teams.id')->all())->toBe([$sysadmins->id]);

    $teamless = DevnotesServer::actingAs(OAuthUser::findOrFail($agentUser->id))->tool(AddNote::class, [
        'title' => 'Whole-pot note', 'body' => 'Body.', 'teams' => [],
    ]);
    $teamless->assertOk();
    expect(Note::where('title', 'Whole-pot note')->sole()->teams()->count())->toBe(0);

    $unknown = DevnotesServer::actingAs(OAuthUser::findOrFail($agentUser->id))->tool(AddNote::class, [
        'title' => 'Bad team note', 'body' => 'Body.', 'teams' => ['made-up-team'],
    ]);
    $unknown->assertHasErrors(['teams.0']);
    expect(Note::where('title', 'Bad team note')->exists())->toBeFalse();
});

it('creates a note owned by the calling user even when the arguments spoof a user_id', function () {
    $otherUser = User::factory()->create();
    $agentUser = User::factory()->create();

    $response = DevnotesServer::actingAs(OAuthUser::findOrFail($agentUser->id))->tool(AddNote::class, [
        'title' => 'Livewire modal gotcha',
        'body' => 'The weird thing, the cause, and the fix.',
        'user_id' => $otherUser->id,
    ]);

    $response->assertOk();
    $response->assertName('add-note');
    $note = Note::sole();
    expect($note->user->is($agentUser))->toBeTrue();
    expect($note->title)->toBe('Livewire modal gotcha');
    expect($note->body)->toBe('The weird thing, the cause, and the fix.');
    $response->assertSee('"id":'.$note->id);
    $response->assertSee('Livewire modal gotcha');
});
