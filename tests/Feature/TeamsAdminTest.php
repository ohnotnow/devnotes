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
    $developers->users()->attach([$admin->id, $regularUser->id]);
    Note::factory(3)->create()->each(fn (Note $note) => $note->teams()->attach($developers));

    $this->actingAs($admin)->get(route('admin.teams'))
        ->assertSuccessful()
        ->assertSeeInOrder(['developers', '2', '3']);

    $this->actingAs($regularUser)->get(route('admin.teams'))->assertForbidden();
});

it('creates and renames teams, rejecting duplicate, blank, and oversized names', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Team::factory()->create(['name' => 'taken']);

    Livewire::actingAs($admin)
        ->test(Teams::class)
        ->call('openCreate')
        ->set('editing.name', 'developers')
        ->call('save')
        ->assertHasNoErrors();
    $team = Team::where('name', 'developers')->sole();

    // Saving without changing the name must pass - the unique rule ignores
    // the team's own row.
    Livewire::actingAs($admin)
        ->test(Teams::class)
        ->call('openEdit', $team->id)
        ->assertSet('editing.name', 'developers')
        ->call('save')
        ->assertHasNoErrors();

    Livewire::actingAs($admin)
        ->test(Teams::class)
        ->call('openEdit', $team->id)
        ->set('editing.name', 'platform')
        ->call('save')
        ->assertHasNoErrors();
    expect($team->refresh()->name)->toBe('platform');

    Livewire::actingAs($admin)
        ->test(Teams::class)
        ->call('openEdit', $team->id)
        ->set('editing.name', 'taken')
        ->call('save')
        ->assertHasErrors(['editing.name']);
    expect($team->refresh()->name)->toBe('platform');

    Livewire::actingAs($admin)
        ->test(Teams::class)
        ->call('openCreate')
        ->set('editing.name', '')
        ->call('save')
        ->assertHasErrors(['editing.name']);

    Livewire::actingAs($admin)
        ->test(Teams::class)
        ->call('openCreate')
        ->set('editing.name', str_repeat('x', 256))
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
    $controlNote = Note::factory()->create(['title' => 'Docker registry mirror config']);
    $controlNote->teams()->attach($survivor);

    Livewire::actingAs($admin)
        ->test(Teams::class)
        ->call('openDelete', $doomed->id)
        ->call('deleteTeam')
        ->assertHasNoErrors();

    expect(Team::where('name', 'doomed')->exists())->toBeFalse();
    expect($member->refresh()->teams()->pluck('teams.id')->all())->toBe([$survivor->id]);
    expect($note->refresh()->teams)->toHaveCount(0);
    expect($controlNote->refresh()->teams()->pluck('teams.id')->all())->toBe([$survivor->id]);
    expect(Note::searchScoped($member, 'docker')->get()->pluck('id')->all())->toEqualCanonicalizing([$note->id, $controlNote->id]);
});
