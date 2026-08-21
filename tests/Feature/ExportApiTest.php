<?php

use App\Models\Note;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns the full export including trashed notes to admin tokens', function () {
    Sanctum::actingAs(User::factory()->admin()->create());
    Note::factory()->create(['title' => 'A live gotcha']);
    Note::factory()->create(['title' => 'A deleted gotcha'])->delete();

    $response = $this->getJson('/api/v1/export');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/json');
    expect($response->json('version'))->toBe(1);
    expect(collect($response->json('notes'))->pluck('title')->all())
        ->toBe(['A live gotcha', 'A deleted gotcha']);
});

it('serves the export in the same encoding as the admin download', function () {
    Sanctum::actingAs(User::factory()->admin()->create());
    Note::factory()->create([
        'title' => 'A gotcha with a link',
        'body' => 'See the [docs](https://example.com/docs) for the naïve version.',
    ]);

    $response = $this->getJson('/api/v1/export');

    $response->assertOk();
    // The Go CLI and the import path both read these bytes: slashes and unicode
    // stay unescaped and the document is pretty-printed.
    expect($response->getContent())->toContain('https://example.com/docs');
    expect($response->getContent())->toContain('naïve');
    expect($response->getContent())->toContain("\n    \"version\": 1,");
    expect($response->getContent())->toBe(json_encode(Note::exportPayload(), Note::EXPORT_JSON_FLAGS));
});

it('refuses the export to non-admin tokens', function () {
    Sanctum::actingAs(User::factory()->create());
    Note::factory()->create(['title' => 'A live gotcha']);

    $response = $this->getJson('/api/v1/export');

    $response->assertForbidden();
    expect(Note::count())->toBe(1);
});

it('refuses the export without a token', function () {
    Note::factory()->create(['title' => 'A live gotcha']);

    $response = $this->getJson('/api/v1/export');

    $response->assertUnauthorized();
    expect($response->json('notes'))->toBeNull();
});
