<?php

use App\Models\Note;
use App\Models\User;

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

it('shows the export link in the sidebar to admins only', function () {
    $admin = User::factory()->admin()->create();
    $regularUser = User::factory()->create();

    $adminPage = $this->actingAs($admin)->get('/');
    $adminPage->assertSuccessful();
    $adminPage->assertSee(route('admin.export'));

    $regularUserPage = $this->actingAs($regularUser)->get('/');
    $regularUserPage->assertSuccessful();
    $regularUserPage->assertDontSee(route('admin.export'));
});
