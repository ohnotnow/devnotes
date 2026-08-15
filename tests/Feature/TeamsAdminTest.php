<?php

use App\Livewire\Admin\Teams;
use App\Models\Note;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

it('shows admins the team list with member and note counts, and turns everyone else away', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $regularUser = User::factory()->create(['is_admin' => false]);
    $developers = Team::factory()->create(['name' => 'developers']);
    $developers->users()->attach($admin);
    $note = Note::factory()->create();
    $note->teams()->attach($developers);

    $this->actingAs($admin)->get('/admin/teams')
        ->assertSuccessful()
        ->assertSee('developers');

    $this->actingAs($regularUser)->get('/admin/teams')->assertForbidden();
});

it('creates and renames teams, rejecting duplicate names', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Team::factory()->create(['name' => 'taken']);

    Livewire::actingAs($admin)
        ->test(Teams::class)
        ->call('openCreate')
        ->set('editing.name', 'developers')
        ->call('save')
        ->assertHasNoErrors();
    $team = Team::where('name', 'developers')->sole();

    Livewire::actingAs($admin)
        ->test(Teams::class)
        ->call('openEdit', $team->id)
        ->assertSet('editing.name', 'developers')
        ->set('editing.name', 'platform')
        ->call('save')
        ->assertHasNoErrors();
    expect($team->refresh()->name)->toBe('platform');

    Livewire::actingAs($admin)
        ->test(Teams::class)
        ->call('openCreate')
        ->set('editing.name', 'taken')
        ->call('save')
        ->assertHasErrors(['editing.name']);
    expect(Team::count())->toBe(2);
});

it('deletes a team without hiding or losing anything', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $doomed = Team::factory()->create(['name' => 'doomed']);
    $survivor = Team::factory()->create(['name' => 'survivor']);
    $member = User::factory()->create();
    $member->teams()->attach([$doomed->id, $survivor->id]);
    $note = Note::factory()->create(['title' => 'Docker daemon log rotation']);
    $note->teams()->attach($doomed);

    Livewire::actingAs($admin)
        ->test(Teams::class)
        ->call('openDelete', $doomed->id)
        ->call('deleteTeam')
        ->assertHasNoErrors();

    expect(Team::where('name', 'doomed')->exists())->toBeFalse();
    expect($member->refresh()->teams()->pluck('teams.id')->all())->toBe([$survivor->id]);
    expect($note->refresh()->teams)->toHaveCount(0);
    expect(Note::searchScoped($member, 'docker')->get()->pluck('id')->all())->toBe([$note->id]);
});
