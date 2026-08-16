<?php

use App\Livewire\NotesIndex;
use App\Models\Note;
use App\Models\User;
use Livewire\Livewire;

it('lets an admin download the export as an attached json file', function () {
    $admin = User::factory()->admin()->create();
    Note::factory()->create(['title' => 'A gotcha worth keeping']);

    $response = $this->actingAs($admin)->get('/admin/export');

    $response->assertSuccessful();
    $response->assertHeader('Content-Type', 'application/json');
    $response->assertHeader('Content-Disposition', 'attachment; filename="devnotes-export-'.now()->toDateString().'.json"');
    expect($response->json('version'))->toBe(1);
    expect($response->json('notes.0.title'))->toBe('A gotcha worth keeping');
});

it('downloads bytes in the same encoding the golden-master contract is stored in', function () {
    $admin = User::factory()->admin()->create();
    Note::factory()->create();

    $response = $this->actingAs($admin)->get('/admin/export');

    $expected = json_encode(Note::exportPayload(), Note::EXPORT_JSON_FLAGS);
    expect($response->getContent())->toBe($expected);
    expect($response->getContent())->toContain('https://example.com/docs');
});

it('refuses the export download to guests and regular users', function () {
    $regularUser = User::factory()->create();
    Note::factory()->create(['title' => 'A gotcha worth keeping']);

    $this->get(route('admin.export'))->assertRedirect(route('login'));

    $response = $this->actingAs($regularUser)->get('/admin/export');
    $response->assertForbidden();
    $response->assertDontSee('A gotcha worth keeping');
});

it('shows the export button on the notes index to admins only', function () {
    $admin = User::factory()->admin()->create();
    $regularUser = User::factory()->create();

    Livewire::actingAs($admin)
        ->test(NotesIndex::class)
        ->assertSee(route('admin.export'));

    Livewire::actingAs($regularUser)
        ->test(NotesIndex::class)
        ->assertDontSee(route('admin.export'));
});
