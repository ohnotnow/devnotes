<?php

use App\Mcp\Servers\DevnotesServer;
use App\Mcp\Tools\AddNote;
use App\Mcp\Tools\GetNote;
use App\Mcp\Tools\SearchNotes;
use App\Mcp\Tools\UpdateNote;
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
    $response->assertSee('"code":"'.$matchingNote->code.'"');
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

it('reports the total match count so a truncated result set is visible', function () {
    $user = User::factory()->create();
    foreach (range(1, 21) as $i) {
        Note::factory()->create(['title' => "Docker gotcha {$i}", 'updated_at' => now()->subDays(30)->addDays($i)]);
    }
    $oldestNote = Note::where('title', 'Docker gotcha 1')->sole();

    $response = DevnotesServer::actingAs(OAuthUser::findOrFail($user->id))->tool(SearchNotes::class, [
        'query' => 'docker',
    ]);

    $response->assertOk();
    $response->assertSee('"total_matches":21');
    // Recency-ordered with a cap of 20, so the oldest match falls off the end.
    $response->assertSee('Docker gotcha 21');
    $response->assertDontSee($oldestNote->code);
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
    $sharedNote = Note::factory()->create(['title' => 'Docker registry auth expiry']);
    $sharedNote->teams()->attach([$developers->id, $sysadmins->id]);
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
    // A note sharing ANY team with the caller is not from outside their teams.
    $response->assertSee('"teams":["developers","sysadmins"]}');
});

it('labels nothing under broader for a caller with no teams', function () {
    $sysadmins = Team::factory()->create(['name' => 'sysadmins']);
    $agentUser = User::factory()->create();
    $sysadminNote = Note::factory()->create(['title' => 'Docker daemon log rotation']);
    $sysadminNote->teams()->attach($sysadmins);

    $response = DevnotesServer::actingAs(OAuthUser::findOrFail($agentUser->id))->tool(SearchNotes::class, [
        'query' => 'docker',
        'broader' => true,
    ]);

    $response->assertOk();
    $response->assertSee('Docker daemon log rotation');
    $response->assertDontSee('from_outside_your_teams');
});

it('returns the full note body via get-note accepting both bare and hash-prefixed codes', function () {
    $user = User::factory()->create();
    $author = User::factory()->create(['forenames' => 'Test', 'surname' => 'Author']);
    Note::factory()->create([
        'code' => 'abq4x',
        'title' => 'The soft-delete FK trap',
        'body' => "Some **markdown** advice.\n\nSee #zde77 too.",
        'user_id' => $author->id,
    ]);

    $bareForm = DevnotesServer::actingAs(OAuthUser::findOrFail($user->id))->tool(GetNote::class, [
        'code' => 'abq4x',
    ]);

    $bareForm->assertOk();
    $bareForm->assertName('get-note');
    $bareForm->assertSee('The soft-delete FK trap');
    $bareForm->assertSee('Some **markdown** advice.');
    $bareForm->assertSee('Test Author');

    $hashForm = DevnotesServer::actingAs(OAuthUser::findOrFail($user->id))->tool(GetNote::class, [
        'code' => '#abq4x',
    ]);

    $hashForm->assertOk();
    $hashForm->assertSee('"code":"abq4x"');
    $hashForm->assertSee('The soft-delete FK trap');
});

it('returns a graceful tool error from get-note for unknown or junk codes', function () {
    $user = User::factory()->create();

    $unknownCode = DevnotesServer::actingAs(OAuthUser::findOrFail($user->id))->tool(GetNote::class, [
        'code' => '#zzzzz',
    ]);

    $unknownCode->assertHasErrors(['No note found with code #zzzzz']);

    $junkCode = DevnotesServer::actingAs(OAuthUser::findOrFail($user->id))->tool(GetNote::class, [
        'code' => '#note-twelve',
    ]);

    $junkCode->assertHasErrors(['No note found with code #note-twelve']);
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

it('returns similar notes from add-note as a dup nudge while still creating', function () {
    $developers = Team::factory()->create(['name' => 'developers']);
    $agentUser = User::factory()->create();
    $agentUser->teams()->attach($developers);
    $existingNote = Note::factory()->create(['title' => 'Docker layer cache misses on multi-stage builds']);
    $existingNote->teams()->attach($developers);

    $response = DevnotesServer::actingAs(OAuthUser::findOrFail($agentUser->id))->tool(AddNote::class, [
        'title' => 'Docker layer cache misses',
        'body' => 'Body.',
    ]);

    $response->assertOk();
    // The exact fragment pins one entry: the existing note, never the new note itself.
    $response->assertSee('"similar_notes":[{"code":"'.$existingNote->code.'","title":"Docker layer cache misses on multi-stage builds"}]');
    $response->assertSee('update-note');
    expect(Note::where('title', 'Docker layer cache misses')->exists())->toBeTrue();
});

it('nudges when titles merely overlap, best overlap first', function () {
    $developers = Team::factory()->create(['name' => 'developers']);
    $agentUser = User::factory()->create();
    $agentUser->teams()->attach($developers);
    $closeNote = Note::factory()->create(['title' => 'Docker layer cache misses on multi-stage builds']);
    $closeNote->teams()->attach($developers);
    $looseNote = Note::factory()->create(['title' => 'Docker daemon log rotation']);
    $looseNote->teams()->attach($developers);

    $response = DevnotesServer::actingAs(OAuthUser::findOrFail($agentUser->id))->tool(AddNote::class, [
        'title' => 'Docker layer cache invalidation',
        'body' => 'Body.',
    ]);

    $response->assertOk();
    // closeNote shares three title words (docker, layer, cache), looseNote one (docker).
    $response->assertSee('"similar_notes":[{"code":"'.$closeNote->code.'","title":"Docker layer cache misses on multi-stage builds"},{"code":"'.$looseNote->code.'","title":"Docker daemon log rotation"}]');
});

it('ignores a lone body-word overlap in the dup nudge', function () {
    // Observed live: 'manifest' deep in an unrelated note's body dragged that
    // note into the nudge. One shared word in a body is coincidence, not
    // similarity - it takes a shared title word, or two body words, to nudge.
    $developers = Team::factory()->create(['name' => 'developers']);
    $agentUser = User::factory()->create();
    $agentUser->teams()->attach($developers);
    $dockerNote = Note::factory()->create([
        'title' => 'Docker layer cache misses on multi-stage builds',
        'body' => 'Copying package manifests before the source keeps the layer cache warm.',
    ]);
    $dockerNote->teams()->attach($developers);

    $response = DevnotesServer::actingAs(OAuthUser::findOrFail($agentUser->id))->tool(AddNote::class, [
        'title' => 'Vite manifest goes stale after npm upgrade',
        'body' => 'Body.',
    ]);

    $response->assertOk();
    $response->assertDontSee('similar_notes');
});

it('still nudges when two title words appear in an existing note\'s body', function () {
    $developers = Team::factory()->create(['name' => 'developers']);
    $agentUser = User::factory()->create();
    $agentUser->teams()->attach($developers);
    $existingNote = Note::factory()->create([
        'title' => 'Npm dependency hoisting surprises',
        'body' => 'Check the vite manifest when builds act oddly.',
    ]);
    $existingNote->teams()->attach($developers);

    $response = DevnotesServer::actingAs(OAuthUser::findOrFail($agentUser->id))->tool(AddNote::class, [
        'title' => 'Vite manifest goes stale after npm upgrade',
        'body' => 'Body.',
    ]);

    $response->assertOk();
    $response->assertSee('"similar_notes":[{"code":"'.$existingNote->code.'","title":"Npm dependency hoisting surprises"}]');
});

it('adds no dup nudge when the title has only short words', function () {
    $user = User::factory()->create();
    Note::factory()->create(['title' => 'Postgres ilike gotcha', 'body' => 'It bit us now and then.']);

    $response = DevnotesServer::actingAs(OAuthUser::findOrFail($user->id))->tool(AddNote::class, [
        'title' => 'Fix it now',
        'body' => 'Body.',
    ]);

    $response->assertOk();
    $response->assertDontSee('similar_notes');
});

it('adds no dup nudge when nothing similar exists', function () {
    $user = User::factory()->create();
    Note::factory()->create(['title' => 'Postgres like-vs-ilike gotcha', 'body' => 'Unrelated body.']);

    $response = DevnotesServer::actingAs(OAuthUser::findOrFail($user->id))->tool(AddNote::class, [
        'title' => 'Flux modal focus loss',
        'body' => 'Body.',
    ]);

    $response->assertOk();
    $response->assertDontSee('similar_notes');
    $response->assertDontSee('hint');
});

it('never suggests out-of-team notes in the dup nudge', function () {
    // Cross-team near-duplicates are legitimate - the nudge is team-scoped only.
    $developers = Team::factory()->create(['name' => 'developers']);
    $sysadmins = Team::factory()->create(['name' => 'sysadmins']);
    $agentUser = User::factory()->create();
    $agentUser->teams()->attach($developers);
    $sysadminNote = Note::factory()->create(['title' => 'Docker layer cache misses on multi-stage builds']);
    $sysadminNote->teams()->attach($sysadmins);

    $response = DevnotesServer::actingAs(OAuthUser::findOrFail($agentUser->id))->tool(AddNote::class, [
        'title' => 'Docker layer cache misses',
        'body' => 'Body.',
    ]);

    $response->assertOk();
    $response->assertDontSee('similar_notes');
    $response->assertDontSee($sysadminNote->code);
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
    $response->assertSee('"code":"'.$note->code.'"');
    $response->assertSee('Livewire modal gotcha');
});

it('lets update-note change anyone\'s note without stealing authorship', function () {
    // Wiki-style authorisation: any user may edit any note.
    $author = User::factory()->create();
    $agentUser = User::factory()->create();
    $note = Note::factory()->create(['code' => 'abq4x', 'title' => 'A gotcha', 'body' => 'Original body.', 'user_id' => $author->id]);

    $response = DevnotesServer::actingAs(OAuthUser::findOrFail($agentUser->id))->tool(UpdateNote::class, [
        'code' => 'abq4x',
        'body' => 'The finding evolved.',
    ]);

    $response->assertOk();
    $response->assertName('update-note');
    $note->refresh();
    expect($note->body)->toBe('The finding evolved.');
    expect($note->title)->toBe('A gotcha');
    expect($note->user->is($author))->toBeTrue();
});

it('rejects invalid update-note input and changes nothing', function () {
    $user = User::factory()->create();
    $note = Note::factory()->create(['code' => 'abq4x', 'title' => 'A gotcha', 'body' => 'Original body.']);

    $neitherGiven = DevnotesServer::actingAs(OAuthUser::findOrFail($user->id))->tool(UpdateNote::class, [
        'code' => 'abq4x',
    ]);
    $neitherGiven->assertHasErrors(['title', 'body']);

    $overlongTitle = DevnotesServer::actingAs(OAuthUser::findOrFail($user->id))->tool(UpdateNote::class, [
        'code' => 'abq4x',
        'title' => str_repeat('x', 256),
    ]);
    $overlongTitle->assertHasErrors(['title']);

    $note->refresh();
    expect($note->title)->toBe('A gotcha');
    expect($note->body)->toBe('Original body.');
});

it('errors update-note on unknown or trashed codes and changes nothing', function () {
    $user = User::factory()->create();
    $note = Note::factory()->create(['code' => 'abq4x', 'body' => 'Original body.']);
    $trashedNote = Note::factory()->create(['code' => 'zde77', 'body' => 'Trashed body.']);
    $trashedNote->delete();

    $unknownCode = DevnotesServer::actingAs(OAuthUser::findOrFail($user->id))->tool(UpdateNote::class, [
        'code' => '#zzzzz',
        'body' => 'Should go nowhere.',
    ]);

    $unknownCode->assertHasErrors(['No note found with code #zzzzz']);
    $unknownCode->assertSee('search-notes');
    expect($note->refresh()->body)->toBe('Original body.');

    // Deleted means removed from editing too - update-note refuses trashed
    // notes just as the API's PATCH does.
    $trashedCode = DevnotesServer::actingAs(OAuthUser::findOrFail($user->id))->tool(UpdateNote::class, [
        'code' => 'zde77',
        'body' => 'Should go nowhere.',
    ]);

    $trashedCode->assertHasErrors(['No note found with code zde77']);
    expect(Note::withTrashed()->find($trashedNote->id)->body)->toBe('Trashed body.');
});

it('moves updated_at on update-note so the note resurfaces in the digest', function () {
    $user = User::factory()->create();
    $note = Note::factory()->create(['code' => 'abq4x', 'updated_at' => now()->subDay()]);
    $originalTimestamp = $note->updated_at;

    DevnotesServer::actingAs(OAuthUser::findOrFail($user->id))->tool(UpdateNote::class, [
        'code' => 'abq4x',
        'body' => 'The finding evolved.',
    ]);

    expect($note->refresh()->updated_at->greaterThan($originalTimestamp))->toBeTrue();
});

it('never exposes the internal note id through the tools', function () {
    $user = User::factory()->create();
    Note::factory()->create(['title' => 'Postgres like-vs-ilike gotcha', 'body' => 'Body words.']);

    $response = DevnotesServer::actingAs(OAuthUser::findOrFail($user->id))->tool(SearchNotes::class, [
        'query' => 'postgres',
    ]);

    $response->assertOk();
    $response->assertDontSee('"id"');
});

it('treats an uppercase code as unknown in get-note, matching the renderer', function () {
    // Codes are lowercase-only by ruling; sqlite compares case-sensitively so this
    // pins the intent. Mind that MySQL's default ci collation would match instead -
    // harmless, but this test documents which behaviour is the designed one.
    $user = User::factory()->create();
    Note::factory()->create(['code' => 'abq4x']);

    $response = DevnotesServer::actingAs(OAuthUser::findOrFail($user->id))->tool(GetNote::class, [
        'code' => '#ABQ4X',
    ]);

    $response->assertHasErrors(['No note found with code #ABQ4X']);
});

it('bumps read tracking when get-note fetches a note', function () {
    $user = User::factory()->create();
    $note = Note::factory()->create(['code' => 'abq4x']);

    DevnotesServer::actingAs(OAuthUser::findOrFail($user->id))->tool(GetNote::class, [
        'code' => 'abq4x',
    ]);

    $note->refresh();
    expect($note->read_count)->toBe(1);
    expect($note->last_read_at)->not->toBeNull();

    // A second read increments - proves it is a running count, not a set-to-1.
    DevnotesServer::actingAs(OAuthUser::findOrFail($user->id))->tool(GetNote::class, [
        'code' => 'abq4x',
    ]);

    expect($note->refresh()->read_count)->toBe(2);
});

it('does not bump read tracking when a search returns the note', function () {
    $user = User::factory()->create();
    $note = Note::factory()->create(['title' => 'Postgres like-vs-ilike gotcha']);

    DevnotesServer::actingAs(OAuthUser::findOrFail($user->id))->tool(SearchNotes::class, [
        'query' => 'postgres',
    ])->assertSee($note->code);

    $note->refresh();
    expect($note->read_count)->toBe(0);
    expect($note->last_read_at)->toBeNull();
});

it('leaves updated_at untouched when a read bumps the heuristics', function () {
    // updated_at drives the session-start digest - a read must never move it.
    $user = User::factory()->create();
    $note = Note::factory()->create(['code' => 'abq4x', 'updated_at' => now()->subDay()]);
    $originalTimestamp = $note->updated_at;

    DevnotesServer::actingAs(OAuthUser::findOrFail($user->id))->tool(GetNote::class, [
        'code' => 'abq4x',
    ]);

    expect($note->refresh()->updated_at)->toEqual($originalTimestamp);
});

it('keeps trashed notes out of search-notes results', function () {
    $user = User::factory()->create();
    $liveNote = Note::factory()->create(['title' => 'Postgres live gotcha']);
    $trashedNote = Note::factory()->create(['title' => 'Postgres binned gotcha']);
    $trashedNote->delete();

    $response = DevnotesServer::actingAs(OAuthUser::findOrFail($user->id))->tool(SearchNotes::class, [
        'query' => 'postgres',
        'broader' => true,
    ]);

    $response->assertOk();
    $response->assertSee($liveNote->code);
    $response->assertDontSee($trashedNote->code);
});

it('returns a soft-deleted note from get-note with deleted_at flagged', function () {
    $user = User::factory()->create();
    $binnedNote = Note::factory()->create(['code' => 'zde77', 'title' => 'A binned gotcha']);
    $binnedNote->delete();
    Note::factory()->create(['code' => 'abq4x']);

    $response = DevnotesServer::actingAs(OAuthUser::findOrFail($user->id))->tool(GetNote::class, [
        'code' => '#zde77',
    ]);

    $response->assertOk();
    $response->assertSee('A binned gotcha');
    $response->assertSee('deleted_at');

    $liveResponse = DevnotesServer::actingAs(OAuthUser::findOrFail($user->id))->tool(GetNote::class, [
        'code' => 'abq4x',
    ]);

    $liveResponse->assertOk();
    $liveResponse->assertDontSee('deleted_at');
});
