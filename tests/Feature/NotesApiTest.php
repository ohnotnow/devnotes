<?php

use App\Models\Note;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('lists notes newest-first in the documented envelope and filters via search', function () {
    Sanctum::actingAs(User::factory()->create());
    $author = User::factory()->create(['forenames' => 'Test', 'surname' => 'Author']);
    Note::factory()->create(['title' => 'An older postgres gotcha', 'user_id' => $author->id, 'created_at' => now()->subDay()]);
    Note::factory()->create(['title' => 'A newer livewire gotcha', 'user_id' => $author->id]);

    $response = $this->getJson('/api/v1/notes');

    $response->assertSuccessful();
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

it('scopes search to the token user\'s teams and widens with broader', function () {
    $developers = Team::factory()->create(['name' => 'developers']);
    $sysadmins = Team::factory()->create(['name' => 'sysadmins']);
    $developer = Sanctum::actingAs(User::factory()->create());
    $developer->teams()->attach($developers);
    $developerNote = Note::factory()->create(['title' => 'Docker layer cache misses']);
    $developerNote->teams()->attach($developers);
    $sysadminNote = Note::factory()->create(['title' => 'Docker daemon log rotation']);
    $sysadminNote->teams()->attach($sysadmins);

    $scoped = $this->getJson('/api/v1/notes?search=docker');
    $scoped->assertSuccessful();
    expect($scoped->json('data'))->toHaveCount(1);
    expect($scoped->json('data.0.code'))->toBe($developerNote->code);

    $broader = $this->getJson('/api/v1/notes?search=docker&broader=1');
    $broader->assertSuccessful();
    expect(collect($broader->json('data'))->pluck('code')->all())->toEqualCanonicalizing([$developerNote->code, $sysadminNote->code]);

    $browsing = $this->getJson('/api/v1/notes');
    $browsing->assertSuccessful();
    expect($browsing->json('data'))->toHaveCount(2);
});

it('shows a single note as raw markdown', function () {
    Sanctum::actingAs(User::factory()->create());
    $note = Note::factory()->create(['body' => "Some **bold** advice.\n\nSee #12 too."]);

    $response = $this->getJson("/api/v1/notes/{$note->code}");

    $response->assertSuccessful();
    expect($response->json('data.body'))->toBe("Some **bold** advice.\n\nSee #12 too.");
    expect($response->json('data.code'))->toBe($note->code);
});

it('lists a note\'s team names in the resource', function () {
    Sanctum::actingAs(User::factory()->create());
    $taggedNote = Note::factory()->create();
    $taggedNote->teams()->attach(Team::factory()->create(['name' => 'developers']));
    $teamlessNote = Note::factory()->create();

    expect($this->getJson("/api/v1/notes/{$taggedNote->code}")->json('data.teams'))->toBe(['developers']);
    expect($this->getJson("/api/v1/notes/{$teamlessNote->code}")->json('data.teams'))->toBe([]);
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

it('attaches teams on create from the payload or the author\'s defaults', function () {
    $developers = Team::factory()->create(['name' => 'developers']);
    $sysadmins = Team::factory()->create(['name' => 'sysadmins']);
    $tokenUser = Sanctum::actingAs(User::factory()->create());
    $tokenUser->teams()->attach($developers);

    $defaulted = $this->postJson('/api/v1/notes', ['title' => 'Defaulted', 'body' => 'Body.']);
    $defaulted->assertCreated();
    expect(Note::where('code', $defaulted->json('data.code'))->sole()->teams()->pluck('teams.id')->all())->toBe([$developers->id]);

    $explicit = $this->postJson('/api/v1/notes', ['title' => 'Explicit', 'body' => 'Body.', 'team_ids' => [$sysadmins->id]]);
    $explicit->assertCreated();
    expect(Note::where('code', $explicit->json('data.code'))->sole()->teams()->pluck('teams.id')->all())->toBe([$sysadmins->id]);

    $invalid = $this->postJson('/api/v1/notes', ['title' => 'Bad team', 'body' => 'Body.', 'team_ids' => [999]]);
    $invalid->assertUnprocessable();
    $invalid->assertJsonValidationErrors(['team_ids.0']);
    expect(Note::count())->toBe(2);
});

it('lets any token holder update any note without stealing authorship', function () {
    $author = User::factory()->create();
    $note = Note::factory()->create(['title' => 'Old titel', 'user_id' => $author->id]);
    Sanctum::actingAs(User::factory()->create());

    $response = $this->putJson("/api/v1/notes/{$note->code}", ['title' => 'Old title, fixed', 'body' => $note->body]);

    $response->assertSuccessful();
    $note->refresh();
    expect($note->title)->toBe('Old title, fixed');
    expect($note->user->is($author))->toBeTrue();

    $invalid = $this->putJson("/api/v1/notes/{$note->code}", ['title' => str_repeat('x', 256), 'body' => '']);
    $invalid->assertUnprocessable();
    $invalid->assertJsonValidationErrors(['title', 'body']);
    expect($note->fresh()->title)->toBe('Old title, fixed');
    expect($note->fresh()->body)->toBe($note->body);
});

it('syncs teams on update only when team_ids is sent', function () {
    $developers = Team::factory()->create(['name' => 'developers']);
    $sysadmins = Team::factory()->create(['name' => 'sysadmins']);
    Sanctum::actingAs(User::factory()->create());
    $note = Note::factory()->create();
    $note->teams()->attach($developers);

    $withoutTeams = $this->putJson("/api/v1/notes/{$note->code}", ['title' => 'Updated', 'body' => $note->body]);
    $withoutTeams->assertSuccessful();
    expect($note->teams()->pluck('teams.id')->all())->toBe([$developers->id]);

    $withTeams = $this->putJson("/api/v1/notes/{$note->code}", ['title' => 'Updated again', 'body' => $note->body, 'team_ids' => [$sysadmins->id]]);
    $withTeams->assertSuccessful();
    expect($note->teams()->pluck('teams.id')->all())->toBe([$sysadmins->id]);

    $cleared = $this->putJson("/api/v1/notes/{$note->code}", ['title' => 'Cleared', 'body' => $note->body, 'team_ids' => []]);
    $cleared->assertSuccessful();
    expect($note->teams()->count())->toBe(0);
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

    $this->getJson("/api/v1/notes/{$noteToDelete->code}")->assertNotFound();
    $this->deleteJson("/api/v1/notes/{$noteToDelete->code}")->assertNotFound();

    $list = $this->getJson('/api/v1/notes');
    expect($list->json('data'))->toHaveCount(1);
    expect($list->json('data.0.code'))->toBe($noteToKeep->code);
});

it('lists every team with id and name for client team discovery', function () {
    Sanctum::actingAs(User::factory()->create());
    $sysadmins = Team::factory()->create(['name' => 'sysadmins']);
    $developers = Team::factory()->create(['name' => 'developers']);

    $response = $this->getJson('/api/v1/teams');

    $response->assertSuccessful();
    expect($response->json('data'))->toBe([
        ['id' => $developers->id, 'name' => 'developers'],
        ['id' => $sysadmins->id, 'name' => 'sysadmins'],
    ]);
});

it('rejects unauthenticated requests on every endpoint', function () {
    $note = Note::factory()->create();

    $this->getJson('/api/v1/notes')->assertUnauthorized();
    $this->getJson('/api/v1/teams')->assertUnauthorized();
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
    $show->assertSuccessful();
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
