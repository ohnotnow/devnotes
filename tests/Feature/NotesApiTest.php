<?php

use App\Enums\ActivityAction;
use App\Models\Activity;
use App\Models\Channel;
use App\Models\Note;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('lists notes newest-first in the documented envelope and filters via search', function () {
    Sanctum::actingAs(User::factory()->create());
    $author = User::factory()->create(['forenames' => 'Test', 'surname' => 'Author']);
    Note::factory()->create(['title' => 'An older postgres gotcha', 'user_id' => $author->id, 'created_at' => now()->subDay()]);
    Note::factory()->create(['title' => 'A newer livewire gotcha', 'user_id' => $author->id]);

    $response = $this->getJson('/api/v1/notes');

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => ['*' => ['code', 'title', 'body', 'author', 'created_at', 'updated_at']],
        'links' => ['first', 'last', 'prev', 'next'],
        'meta' => ['current_page', 'from', 'last_page', 'path', 'per_page', 'to', 'total'],
    ]);
    expect($response->json('meta.per_page'))->toBe(20);
    expect($response->json('data'))->toHaveCount(2);
    expect($response->json('data.0.title'))->toBe('A newer livewire gotcha');
    expect($response->json('data.1.title'))->toBe('An older postgres gotcha');
    expect($response->json('data.0.author'))->toBe('Test Author');

    $search = $this->getJson('/api/v1/notes?search=postgres');
    expect($search->json('data'))->toHaveCount(1);
    expect($search->json('data.0.title'))->toBe('An older postgres gotcha');
});

it('scopes search and the list to the token user\'s channels and widens with broader', function () {
    $developers = Channel::factory()->create(['name' => 'developers']);
    $sysadmins = Channel::factory()->create(['name' => 'sysadmins']);
    $developer = Sanctum::actingAs(User::factory()->create());
    $developer->channels()->attach($developers);
    $developerNote = Note::factory()->create(['title' => 'Docker layer cache misses']);
    $developerNote->channels()->attach($developers);
    $sysadminNote = Note::factory()->create(['title' => 'Docker daemon log rotation']);
    $sysadminNote->channels()->attach($sysadmins);
    $channellessNote = Note::factory()->create(['title' => 'Compose healthcheck gotcha']);

    $scoped = $this->getJson('/api/v1/notes?search=docker');
    $scoped->assertOk();
    expect($scoped->json('data'))->toHaveCount(1);
    expect($scoped->json('data.0.code'))->toBe($developerNote->code);

    $broader = $this->getJson('/api/v1/notes?search=docker&broader=1');
    $broader->assertOk();
    expect(collect($broader->json('data'))->pluck('code')->all())->toEqualCanonicalizing([$developerNote->code, $sysadminNote->code]);

    $browsing = $this->getJson('/api/v1/notes');
    $browsing->assertOk();
    expect(collect($browsing->json('data'))->pluck('code')->all())->toEqualCanonicalizing([$developerNote->code, $channellessNote->code]);

    $browsingBroader = $this->getJson('/api/v1/notes?broader=1');
    $browsingBroader->assertOk();
    expect(collect($browsingBroader->json('data'))->pluck('code')->all())->toEqualCanonicalizing([$developerNote->code, $sysadminNote->code, $channellessNote->code]);

    Sanctum::actingAs(User::factory()->create());
    $noChannelUserBrowsing = $this->getJson('/api/v1/notes');
    expect(collect($noChannelUserBrowsing->json('data'))->pluck('code')->all())->toEqualCanonicalizing([$developerNote->code, $sysadminNote->code, $channellessNote->code]);
});

it('shows a single note as raw markdown', function () {
    Sanctum::actingAs(User::factory()->create());
    $note = Note::factory()->create(['body' => "Some **bold** advice.\n\nSee #12 too."]);

    $response = $this->getJson("/api/v1/notes/{$note->code}");

    $response->assertOk();
    expect($response->json('data.body'))->toBe("Some **bold** advice.\n\nSee #12 too.");
    expect($response->json('data.code'))->toBe($note->code);
});

it('bumps read tracking when a note is fetched via the api', function () {
    Sanctum::actingAs(User::factory()->create());
    $note = Note::factory()->create();

    $this->getJson("/api/v1/notes/{$note->code}")->assertOk();

    $note->refresh();
    expect($note->read_count)->toBe(1);
    expect($note->last_read_at)->not->toBeNull();
});

it('lists a note\'s channel names in the resource', function () {
    Sanctum::actingAs(User::factory()->create());
    $taggedNote = Note::factory()->create();
    $taggedNote->channels()->attach(Channel::factory()->create(['name' => 'developers']));
    $channellessNote = Note::factory()->create();

    expect($this->getJson("/api/v1/notes/{$taggedNote->code}")->json('data.channels'))->toBe(['developers']);
    expect($this->getJson("/api/v1/notes/{$channellessNote->code}")->json('data.channels'))->toBe([]);
});

it('creates a note owned by the token user and rejects invalid payloads', function () {
    $tokenUser = Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson('/api/v1/notes', ['title' => 'From the cli', 'body' => 'Captured mid-session.']);

    $response->assertCreated();
    $note = Note::sole();
    expect($note->user->is($tokenUser))->toBeTrue();
    expect($note->title)->toBe('From the cli');
    expect($note->body)->toBe('Captured mid-session.');
    expect($response->json('data.code'))->toBe($note->code);
    expect($response->json('data.title'))->toBe('From the cli');
    expect($response->json('data.author'))->toBe($tokenUser->full_name);

    $invalid = $this->postJson('/api/v1/notes', ['title' => str_repeat('x', 256), 'body' => '']);
    $invalid->assertUnprocessable();
    $invalid->assertJsonValidationErrors(['title', 'body']);
    expect(Note::count())->toBe(1);
});

it('attaches channels on create from the payload or the author\'s defaults', function () {
    $developers = Channel::factory()->create(['name' => 'developers']);
    $sysadmins = Channel::factory()->create(['name' => 'sysadmins']);
    $tokenUser = Sanctum::actingAs(User::factory()->create());
    $tokenUser->channels()->attach($developers);

    $defaulted = $this->postJson('/api/v1/notes', ['title' => 'Defaulted', 'body' => 'Body.']);
    $defaulted->assertCreated();
    expect(Note::where('code', $defaulted->json('data.code'))->sole()->channels()->pluck('channels.id')->all())->toBe([$developers->id]);

    $explicit = $this->postJson('/api/v1/notes', ['title' => 'Explicit', 'body' => 'Body.', 'channel_ids' => [$sysadmins->id]]);
    $explicit->assertCreated();
    expect(Note::where('code', $explicit->json('data.code'))->sole()->channels()->pluck('channels.id')->all())->toBe([$sysadmins->id]);

    $invalid = $this->postJson('/api/v1/notes', ['title' => 'Bad team', 'body' => 'Body.', 'channel_ids' => [999]]);
    $invalid->assertUnprocessable();
    $invalid->assertJsonValidationErrors(['channel_ids.0']);
    expect(Note::count())->toBe(2);
});

it('lets any token holder update any note without stealing authorship', function () {
    $author = User::factory()->create();
    $note = Note::factory()->create(['title' => 'Old titel', 'user_id' => $author->id]);
    Sanctum::actingAs(User::factory()->create());

    $response = $this->putJson("/api/v1/notes/{$note->code}", ['title' => 'Old title, fixed', 'body' => $note->body]);

    $response->assertOk();
    $note->refresh();
    expect($note->title)->toBe('Old title, fixed');
    expect($note->user->is($author))->toBeTrue();

    $invalid = $this->putJson("/api/v1/notes/{$note->code}", ['title' => str_repeat('x', 256), 'body' => '']);
    $invalid->assertUnprocessable();
    $invalid->assertJsonValidationErrors(['title', 'body']);
    expect($note->fresh()->title)->toBe('Old title, fixed');
    expect($note->fresh()->body)->toBe($note->body);
});

it('syncs channels on update only when channel_ids is sent', function () {
    $developers = Channel::factory()->create(['name' => 'developers']);
    $sysadmins = Channel::factory()->create(['name' => 'sysadmins']);
    Sanctum::actingAs(User::factory()->create());
    $note = Note::factory()->create();
    $note->channels()->attach($developers);

    $withoutChannels = $this->putJson("/api/v1/notes/{$note->code}", ['title' => 'Updated', 'body' => $note->body]);
    $withoutChannels->assertOk();
    expect($note->channels()->pluck('channels.id')->all())->toBe([$developers->id]);

    $withChannels = $this->putJson("/api/v1/notes/{$note->code}", ['title' => 'Updated again', 'body' => $note->body, 'channel_ids' => [$sysadmins->id]]);
    $withChannels->assertOk();
    expect($note->channels()->pluck('channels.id')->all())->toBe([$sysadmins->id]);

    $cleared = $this->putJson("/api/v1/notes/{$note->code}", ['title' => 'Cleared', 'body' => $note->body, 'channel_ids' => []]);
    $cleared->assertOk();
    expect($note->channels()->count())->toBe(0);
});

it('shows a trashed note via the api with deleted_at set', function () {
    Sanctum::actingAs(User::factory()->create());
    $trashedNote = Note::factory()->create();
    $trashedNote->delete();
    $liveNote = Note::factory()->create();

    $trashedResponse = $this->getJson("/api/v1/notes/{$trashedNote->code}");
    $trashedResponse->assertOk();
    expect($trashedResponse->json('data.deleted_at'))->not->toBeNull();

    expect($this->getJson("/api/v1/notes/{$liveNote->code}")->json('data.deleted_at'))->toBeNull();
});

it('soft-deletes exactly the targeted note', function () {
    Sanctum::actingAs(User::factory()->create());
    $noteToDelete = Note::factory()->create();
    $noteToKeep = Note::factory()->create();

    $response = $this->deleteJson("/api/v1/notes/{$noteToDelete->code}");

    $response->assertNoContent();
    expect(Note::find($noteToDelete->id))->toBeNull();
    expect(Note::withTrashed()->find($noteToDelete->id))->not->toBeNull();
    expect(Note::find($noteToKeep->id))->not->toBeNull();

    // Deletion removes a note from discovery, not citation: show keeps working
    // (with deleted_at set), while update and destroy refuse trashed notes.
    $this->getJson("/api/v1/notes/{$noteToDelete->code}")->assertOk();
    $this->putJson("/api/v1/notes/{$noteToDelete->code}", ['title' => 'Nope', 'body' => 'Nope.'])->assertNotFound();
    $this->deleteJson("/api/v1/notes/{$noteToDelete->code}")->assertNotFound();

    $list = $this->getJson('/api/v1/notes');
    expect($list->json('data'))->toHaveCount(1);
    expect($list->json('data.0.code'))->toBe($noteToKeep->code);
});

it('lists every channel with id and name for client channel discovery', function () {
    Sanctum::actingAs(User::factory()->create());
    $sysadmins = Channel::factory()->create(['name' => 'sysadmins']);
    $developers = Channel::factory()->create(['name' => 'developers']);

    $response = $this->getJson('/api/v1/channels');

    $response->assertOk();
    expect($response->json('data'))->toBe([
        ['id' => $developers->id, 'name' => 'developers'],
        ['id' => $sysadmins->id, 'name' => 'sysadmins'],
    ]);
});

it('rejects unauthenticated requests on every endpoint', function () {
    $note = Note::factory()->create();

    $this->getJson('/api/v1/notes')->assertUnauthorized();
    $this->getJson('/api/v1/channels')->assertUnauthorized();
    $this->getJson("/api/v1/notes/{$note->code}")->assertUnauthorized();
    $this->postJson('/api/v1/notes', [])->assertUnauthorized();
    $this->putJson("/api/v1/notes/{$note->code}", [])->assertUnauthorized();
    $this->deleteJson("/api/v1/notes/{$note->code}")->assertUnauthorized();
});

it('never exposes the internal note id and refuses id-based URLs', function () {
    Sanctum::actingAs(User::factory()->create());
    $note = Note::factory()->create(['code' => 'abq4x', 'title' => 'A coded gotcha']);

    $list = $this->getJson('/api/v1/notes');
    expect($list->json('data.0.code'))->toBe('abq4x');
    expect($list->json('data.0'))->not->toHaveKey('id');

    $show = $this->getJson('/api/v1/notes/abq4x');
    $show->assertOk();
    expect($show->json('data'))->not->toHaveKey('id');

    $this->getJson("/api/v1/notes/{$note->id}")->assertNotFound();
});

it('ignores client-supplied code and ulid on create', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson('/api/v1/notes', [
        'title' => 'Sneaky identity',
        'body' => 'Trying to mint my own.',
        'code' => 'abq4x',
        'ulid' => '01ARZ3NDEKTSV4RRFFQ69G5FA1',
    ]);

    $response->assertCreated();
    $note = Note::sole();
    expect($note->code)->not->toBe('abq4x');
    expect($note->ulid)->not->toBe('01ARZ3NDEKTSV4RRFFQ69G5FA1');
});

it('authenticates a real bearer token end to end and refuses it once revoked', function () {
    $tokenUser = User::factory()->create();
    $plainTextToken = $tokenUser->createToken('cli on my laptop')->plainTextToken;
    Note::factory()->create(['title' => 'Reachable with a token']);

    $this->getJson('/api/v1/notes')->assertUnauthorized();
    $this->withToken('not-a-real-token')->getJson('/api/v1/notes')->assertUnauthorized();

    $authenticated = $this->withToken($plainTextToken)->getJson('/api/v1/notes');
    $authenticated->assertOk();
    expect($authenticated->json('data.0.title'))->toBe('Reachable with a token');

    $created = $this->withToken($plainTextToken)->postJson('/api/v1/notes', ['title' => 'From the cli', 'body' => 'Body.']);
    $created->assertCreated();
    expect(Note::where('code', $created->json('data.code'))->sole()->user->is($tokenUser))->toBeTrue();

    expect(Note::count())->toBe(2);
});

it('refuses a token that has been revoked', function () {
    $tokenUser = User::factory()->create();
    $plainTextToken = $tokenUser->createToken('cli on my laptop')->plainTextToken;

    $tokenUser->tokens()->delete();

    $this->withToken($plainTextToken)->getJson('/api/v1/notes')->assertUnauthorized();
});

it('updates a note over PATCH the same way it does over PUT', function () {
    $developers = Channel::factory()->create(['name' => 'developers']);
    $author = User::factory()->create();
    $note = Note::factory()->create(['title' => 'Old titel', 'body' => 'Body.', 'user_id' => $author->id]);
    Sanctum::actingAs(User::factory()->create());

    $response = $this->patchJson("/api/v1/notes/{$note->code}", [
        'title' => 'Old title, fixed',
        'body' => 'Body.',
        'channel_ids' => [$developers->id],
    ]);

    $response->assertOk();
    $note->refresh();
    expect($note->title)->toBe('Old title, fixed');
    expect($note->user->is($author))->toBeTrue();
    expect($note->channels()->pluck('channels.id')->all())->toBe([$developers->id]);

    // PATCH carries the same full-payload contract as PUT - a partial one is rejected.
    $partial = $this->patchJson("/api/v1/notes/{$note->code}", ['title' => 'Title only']);
    $partial->assertUnprocessable();
    $partial->assertJsonValidationErrors(['body']);
    expect($note->fresh()->title)->toBe('Old title, fixed');
});

it('records who did what when notes are written through the api', function () {
    $tokenUser = User::factory()->create();
    $plainTextToken = $tokenUser->createToken('cli')->plainTextToken;

    $created = $this->withToken($plainTextToken)->postJson('/api/v1/notes', ['title' => 'Logged create', 'body' => 'Body.']);
    $created->assertCreated();

    $activity = Activity::sole();
    expect($activity->user->is($tokenUser))->toBeTrue();
    expect($activity->action)->toBe(ActivityAction::Created);

    $this->withToken($plainTextToken)->putJson("/api/v1/notes/{$created->json('data.code')}", ['title' => 'Logged edit', 'body' => 'Body.'])->assertOk();
    $this->withToken($plainTextToken)->deleteJson("/api/v1/notes/{$created->json('data.code')}")->assertNoContent();

    expect(Activity::pluck('action')->all())->toBe([ActivityAction::Created, ActivityAction::Edited, ActivityAction::Deleted]);
    expect(Activity::pluck('user_id')->unique()->all())->toBe([$tokenUser->id]);
});

it('pages through the list with working links and meta', function () {
    Sanctum::actingAs(User::factory()->create());
    // Distinct created_at values: the list is ordered by latest(), so ties
    // would leave which note lands on page two up to the database.
    foreach (range(1, 21) as $minutesOld) {
        Note::factory()->create(['created_at' => now()->subMinutes($minutesOld)]);
    }
    $oldestNote = Note::orderBy('created_at')->first();

    $firstPage = $this->getJson('/api/v1/notes');

    $firstPage->assertOk();
    expect($firstPage->json('data'))->toHaveCount(20);
    expect($firstPage->json('meta.total'))->toBe(21);
    expect($firstPage->json('meta.last_page'))->toBe(2);
    expect($firstPage->json('links.prev'))->toBeNull();
    expect($firstPage->json('links.next'))->toContain('page=2');

    $secondPage = $this->getJson('/api/v1/notes?page=2');

    $secondPage->assertOk();
    expect($secondPage->json('data'))->toHaveCount(1);
    expect($secondPage->json('data.0.code'))->toBe($oldestNote->code);
    expect($secondPage->json('meta.current_page'))->toBe(2);
    expect($secondPage->json('links.next'))->toBeNull();
});
