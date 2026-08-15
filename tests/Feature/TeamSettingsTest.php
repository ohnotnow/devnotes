<?php

use App\Livewire\TeamSettings;
use App\Models\Note;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

it('shows the user\'s current subscriptions and note defaults pre-checked', function () {
    $developers = Team::factory()->create(['name' => 'developers']);
    $security = Team::factory()->create(['name' => 'security']);
    $member = User::factory()->create();
    $member->teams()->attach([$developers->id, $security->id]);
    $member->teams()->updateExistingPivot($security->id, ['note_default' => false]);

    Livewire::actingAs($member)
        ->test(TeamSettings::class)
        ->assertSee('developers')
        ->assertSee('security')
        ->assertSet('subscribedTeamIds', [$developers->id, $security->id])
        ->assertSet('defaultTeamIds', [$developers->id]);
});

it('saves diverging subscriptions and note defaults, dropping teams unchecked in both', function () {
    $developers = Team::factory()->create(['name' => 'developers']);
    $security = Team::factory()->create(['name' => 'security']);
    $lapsed = Team::factory()->create(['name' => 'lapsed']);
    $writeOnly = Team::factory()->create(['name' => 'write-only']);
    $member = User::factory()->create();
    $member->teams()->attach([$developers->id, $lapsed->id]);

    Livewire::actingAs($member)
        ->test(TeamSettings::class)
        ->set('subscribedTeamIds', [$developers->id, $security->id])
        ->set('defaultTeamIds', [$developers->id, $writeOnly->id])
        ->call('save')
        ->assertHasNoErrors();

    $member->refresh();
    expect($member->subscribedTeams()->pluck('teams.id')->all())->toEqualCanonicalizing([$developers->id, $security->id]);
    expect($member->defaultNoteTeams()->pluck('teams.id')->all())->toEqualCanonicalizing([$developers->id, $writeOnly->id]);
    expect($member->teams()->pluck('teams.id')->all())->toEqualCanonicalizing([$developers->id, $security->id, $writeOnly->id]);
});

it('rejects a team id that does not exist without saving anything', function () {
    $developers = Team::factory()->create(['name' => 'developers']);
    $member = User::factory()->create();
    $member->teams()->attach($developers);

    Livewire::actingAs($member)
        ->test(TeamSettings::class)
        ->set('subscribedTeamIds', [999])
        ->call('save')
        ->assertHasErrors(['subscribedTeamIds.0']);

    expect($member->refresh()->teams()->pluck('teams.id')->all())->toBe([$developers->id]);
});

it('changes what scoped search shows once saved', function () {
    $developers = Team::factory()->create(['name' => 'developers']);
    $sysadmins = Team::factory()->create(['name' => 'sysadmins']);
    $member = User::factory()->create();
    $member->teams()->attach([$developers->id, $sysadmins->id]);
    $sysadminNote = Note::factory()->create(['title' => 'Docker daemon log rotation']);
    $sysadminNote->teams()->attach($sysadmins);

    expect(Note::searchScoped($member->fresh(), 'docker')->get()->pluck('id')->all())->toBe([$sysadminNote->id]);

    Livewire::actingAs($member)
        ->test(TeamSettings::class)
        ->set('subscribedTeamIds', [$developers->id])
        ->set('defaultTeamIds', [$developers->id])
        ->call('save')
        ->assertHasNoErrors();

    expect(Note::searchScoped($member->fresh(), 'docker')->get())->toHaveCount(0);
});

it('renders the full page for a logged-in user and requires login otherwise', function () {
    $member = User::factory()->create();

    $this->get(route('team-settings'))->assertRedirect(route('login'));

    $this->actingAs($member)->get(route('team-settings'))->assertSuccessful();
});
