<?php

use App\Models\Note;
use App\Models\Team;
use App\Models\User;

it('hides another team\'s matching note from a scoped search', function () {
    $developers = Team::factory()->create(['name' => 'developers']);
    $sysadmins = Team::factory()->create(['name' => 'sysadmins']);
    $developer = User::factory()->create();
    $developer->teams()->attach($developers);
    $developerNote = Note::factory()->create(['title' => 'Docker layer cache misses']);
    $developerNote->teams()->attach($developers);
    $sysadminNote = Note::factory()->create(['title' => 'Docker daemon log rotation']);
    $sysadminNote->teams()->attach($sysadmins);

    $results = Note::searchScoped($developer, 'docker')->get();

    expect($results->pluck('id'))->toContain($developerNote->id);
    expect($results->pluck('id'))->not->toContain($sysadminNote->id);
});

it('shows teamless notes in every scoped search', function () {
    $developers = Team::factory()->create(['name' => 'developers']);
    $developer = User::factory()->create();
    $developer->teams()->attach($developers);
    $teamlessNote = Note::factory()->create(['title' => 'Docker compose healthcheck gotcha']);

    $results = Note::searchScoped($developer, 'docker')->get();

    expect($results->pluck('id'))->toContain($teamlessNote->id);
});

it('searches the whole pot for a user with no subscriptions', function () {
    $sysadmins = Team::factory()->create(['name' => 'sysadmins']);
    $userWithoutTeams = User::factory()->create();
    $sysadminNote = Note::factory()->create(['title' => 'Docker daemon log rotation']);
    $sysadminNote->teams()->attach($sysadmins);

    $results = Note::searchScoped($userWithoutTeams, 'docker')->get();

    expect($results->pluck('id'))->toContain($sysadminNote->id);
});

it('returns cross-team matches when broader', function () {
    $developers = Team::factory()->create(['name' => 'developers']);
    $sysadmins = Team::factory()->create(['name' => 'sysadmins']);
    $developer = User::factory()->create();
    $developer->teams()->attach($developers);
    $sysadminNote = Note::factory()->create(['title' => 'Docker daemon log rotation']);
    $sysadminNote->teams()->attach($sysadmins);

    $scoped = Note::searchScoped($developer, 'docker')->get();
    $broader = Note::searchScoped($developer, 'docker', broader: true)->get();

    expect($scoped->pluck('id'))->not->toContain($sysadminNote->id);
    expect($broader->pluck('id'))->toContain($sysadminNote->id);
});

it('defaults a note\'s teams to the author\'s note defaults', function () {
    $developers = Team::factory()->create(['name' => 'developers']);
    $security = Team::factory()->create(['name' => 'security']);
    $author = User::factory()->create();
    $author->teams()->attach([$developers->id, $security->id]);
    $author->teams()->updateExistingPivot($security->id, ['note_default' => false]);
    $note = Note::factory()->create(['user_id' => $author->id]);

    $note->assignTeams();

    expect($note->teams()->pluck('teams.id')->all())->toBe([$developers->id]);
});

it('assigns exactly the explicit teams when given', function () {
    $developers = Team::factory()->create(['name' => 'developers']);
    $sysadmins = Team::factory()->create(['name' => 'sysadmins']);
    $author = User::factory()->create();
    $author->teams()->attach($developers);
    $note = Note::factory()->create(['user_id' => $author->id]);

    $note->assignTeams([$sysadmins->id]);

    expect($note->teams()->pluck('teams.id')->all())->toBe([$sysadmins->id]);
});
