<?php

use App\Models\Note;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns the full export including trashed notes to admin tokens', function () {
    Sanctum::actingAs(User::factory()->admin()->create());
    Note::factory()->create(['title' => 'A live gotcha']);
    Note::factory()->create(['title' => 'A deleted gotcha'])->delete();

    $response = $this->getJson('/api/v1/export');

    $response->assertSuccessful();
    expect($response->json('version'))->toBe(1);
    expect(collect($response->json('notes'))->pluck('title')->all())
        ->toBe(['A live gotcha', 'A deleted gotcha']);
});

it('refuses the export to non-admin tokens', function () {
    Sanctum::actingAs(User::factory()->create());
    Note::factory()->create(['title' => 'A live gotcha']);

    $response = $this->getJson('/api/v1/export');

    $response->assertForbidden();
    $response->assertDontSee('A live gotcha');
});

it('refuses the export without a token', function () {
    Note::factory()->create(['title' => 'A live gotcha']);

    $response = $this->getJson('/api/v1/export');

    $response->assertUnauthorized();
    $response->assertDontSee('A live gotcha');
});
