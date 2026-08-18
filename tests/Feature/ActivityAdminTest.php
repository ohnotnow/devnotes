<?php

use App\Livewire\Admin\ActivityLog;
use App\Models\Note;
use App\Models\User;
use Livewire\Livewire;

it('shows the activity log to admins and refuses everyone else', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $regularUser = User::factory()->create();

    $this->actingAs($admin)->get(route('admin.activity'))->assertOk();
    $this->actingAs($regularUser)->get(route('admin.activity'))->assertForbidden();
});

it('lists activity with who, what and when', function () {
    $editor = User::factory()->create(['forenames' => 'Eddie', 'surname' => 'Editor']);
    $this->actingAs($editor);
    Note::factory()->create(['code' => 'abq4x', 'title' => 'Puppet client on Rocky']);

    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin)->get(route('admin.activity'))
        ->assertSee('Eddie Editor')
        ->assertSee('Created')
        ->assertSee("created note #abq4x 'Puppet client on Rocky'");
});

it('filters activity by a search over the message and the user name', function () {
    $puppetAuthor = User::factory()->create(['forenames' => 'Vera', 'surname' => 'Viewer']);
    $this->actingAs($puppetAuthor);
    Note::factory()->create(['code' => 'abq4x', 'title' => 'Puppet modules']);
    $dockerAuthor = User::factory()->create(['forenames' => 'Older', 'surname' => 'Author']);
    $this->actingAs($dockerAuthor);
    Note::factory()->create(['code' => 'zde77', 'title' => 'Docker cache']);
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)->test(ActivityLog::class)
        ->set('search', '#abq4x')
        ->assertSee("created note #abq4x 'Puppet modules'")
        ->assertDontSee('Docker cache');

    Livewire::actingAs($admin)->test(ActivityLog::class)
        ->set('search', 'older')
        ->assertSee('Docker cache')
        ->assertDontSee('Puppet modules');
});

it('filters activity by action so reads cannot drown out edits', function () {
    $author = User::factory()->create();
    $this->actingAs($author);
    $note = Note::factory()->create(['code' => 'abq4x', 'title' => 'Puppet modules']);
    $note->update(['title' => 'Puppet modules v2']);
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)->test(ActivityLog::class)
        ->set('action', 'edited')
        ->assertSee("edited note #abq4x 'Puppet modules v2'")
        ->assertDontSee("created note #abq4x 'Puppet modules'");
});
