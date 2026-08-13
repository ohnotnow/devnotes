<?php

use App\Models\Note;
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
        'data' => ['*' => ['id', 'title', 'body', 'author', 'created_at', 'updated_at']],
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

it('shows a single note as raw markdown', function () {
    Sanctum::actingAs(User::factory()->create());
    $note = Note::factory()->create(['body' => "Some **bold** advice.\n\nSee #12 too."]);

    $response = $this->getJson("/api/v1/notes/{$note->id}");

    $response->assertSuccessful();
    expect($response->json('data.body'))->toBe("Some **bold** advice.\n\nSee #12 too.");
    expect($response->json('data.id'))->toBe($note->id);
});

it('creates a note owned by the token user and rejects invalid payloads', function () {
    $tokenUser = Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson('/api/v1/notes', ['title' => 'From the cli', 'body' => 'Captured mid-session.']);

    $response->assertCreated();
    $note = Note::sole();
    expect($note->user->is($tokenUser))->toBeTrue();
    expect($note->title)->toBe('From the cli');
    expect($note->body)->toBe('Captured mid-session.');
    expect($response->json('data.id'))->toBe($note->id);
    expect($response->json('data.title'))->toBe('From the cli');
    expect($response->json('data.author'))->toBe($tokenUser->full_name);

    $invalid = $this->postJson('/api/v1/notes', ['title' => str_repeat('x', 256), 'body' => '']);
    $invalid->assertUnprocessable();
    $invalid->assertJsonValidationErrors(['title', 'body']);
    expect(Note::count())->toBe(1);
});

it('lets any token holder update any note without stealing authorship', function () {
    $author = User::factory()->create();
    $note = Note::factory()->create(['title' => 'Old titel', 'user_id' => $author->id]);
    Sanctum::actingAs(User::factory()->create());

    $response = $this->putJson("/api/v1/notes/{$note->id}", ['title' => 'Old title, fixed', 'body' => $note->body]);

    $response->assertSuccessful();
    $note->refresh();
    expect($note->title)->toBe('Old title, fixed');
    expect($note->user->is($author))->toBeTrue();

    $invalid = $this->putJson("/api/v1/notes/{$note->id}", ['title' => str_repeat('x', 256), 'body' => '']);
    $invalid->assertUnprocessable();
    $invalid->assertJsonValidationErrors(['title', 'body']);
    expect($note->fresh()->title)->toBe('Old title, fixed');
});

it('soft-deletes exactly the targeted note', function () {
    Sanctum::actingAs(User::factory()->create());
    $noteToDelete = Note::factory()->create();
    $noteToKeep = Note::factory()->create();

    $response = $this->deleteJson("/api/v1/notes/{$noteToDelete->id}");

    $response->assertNoContent();
    expect(Note::find($noteToDelete->id))->toBeNull();
    expect(Note::withTrashed()->find($noteToDelete->id))->not->toBeNull();
    expect(Note::find($noteToKeep->id))->not->toBeNull();

    $this->getJson("/api/v1/notes/{$noteToDelete->id}")->assertNotFound();
    $this->deleteJson("/api/v1/notes/{$noteToDelete->id}")->assertNotFound();

    $list = $this->getJson('/api/v1/notes');
    expect($list->json('data'))->toHaveCount(1);
    expect($list->json('data.0.id'))->toBe($noteToKeep->id);
});

it('rejects unauthenticated requests on every endpoint', function () {
    $note = Note::factory()->create();

    $this->getJson('/api/v1/notes')->assertUnauthorized();
    $this->getJson("/api/v1/notes/{$note->id}")->assertUnauthorized();
    $this->postJson('/api/v1/notes', [])->assertUnauthorized();
    $this->putJson("/api/v1/notes/{$note->id}", [])->assertUnauthorized();
    $this->deleteJson("/api/v1/notes/{$note->id}")->assertUnauthorized();
});
