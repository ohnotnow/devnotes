<?php

use App\Mcp\Servers\DevnotesServer;
use App\Mcp\Tools\AddNote;
use App\Mcp\Tools\GetNote;
use App\Mcp\Tools\SearchNotes;
use App\Models\Note;
use App\Models\OAuthUser;
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
    $noMatches->assertSee('[]');
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
