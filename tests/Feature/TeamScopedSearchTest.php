<?php

use App\Models\Note;
use App\Models\Team;
use App\Models\User;

it('scopes search to subscribed teams only', function () {
    $developers = Team::factory()->create(['name' => 'developers']);
    $frontend = Team::factory()->create(['name' => 'frontend']);
    $sysadmins = Team::factory()->create(['name' => 'sysadmins']);
    $lapsed = Team::factory()->create(['name' => 'lapsed']);
    $developer = User::factory()->create();
    $developer->teams()->attach([$developers->id, $frontend->id, $lapsed->id]);
    $developer->teams()->updateExistingPivot($lapsed->id, ['subscribed' => false]);
    $developerNote = Note::factory()->create(['title' => 'Docker layer cache misses']);
    $developerNote->teams()->attach($developers);
    $frontendNote = Note::factory()->create(['title' => 'Docker vite dev-server networking']);
    $frontendNote->teams()->attach($frontend);
    $sysadminNote = Note::factory()->create(['title' => 'Docker daemon log rotation']);
    $sysadminNote->teams()->attach($sysadmins);
    $lapsedNote = Note::factory()->create(['title' => 'Docker swarm leftovers']);
    $lapsedNote->teams()->attach($lapsed);

    $results = Note::searchScoped($developer, 'docker')->get();

    expect($results->pluck('id')->all())->toEqualCanonicalizing([$developerNote->id, $frontendNote->id]);
});

it('shows teamless notes in a scoped search that excludes other teams', function () {
    $developers = Team::factory()->create(['name' => 'developers']);
    $sysadmins = Team::factory()->create(['name' => 'sysadmins']);
    $developer = User::factory()->create();
    $developer->teams()->attach($developers);
    $teamlessNote = Note::factory()->create(['title' => 'Docker compose healthcheck gotcha']);
    $sysadminNote = Note::factory()->create(['title' => 'Docker daemon log rotation']);
    $sysadminNote->teams()->attach($sysadmins);

    $results = Note::searchScoped($developer, 'docker')->get();

    expect($results->pluck('id')->all())->toEqualCanonicalizing([$teamlessNote->id]);
});

it('searches the whole pot for a user with no subscriptions', function () {
    $sysadmins = Team::factory()->create(['name' => 'sysadmins']);
    $userWithoutTeams = User::factory()->create();
    $sysadminNote = Note::factory()->create(['title' => 'Docker daemon log rotation']);
    $sysadminNote->teams()->attach($sysadmins);

    $results = Note::searchScoped($userWithoutTeams, 'docker')->get();

    expect($results->pluck('id')->all())->toEqualCanonicalizing([$sysadminNote->id]);
});

it('returns cross-team matches when broader', function () {
    $developers = Team::factory()->create(['name' => 'developers']);
    $sysadmins = Team::factory()->create(['name' => 'sysadmins']);
    $developer = User::factory()->create();
    $developer->teams()->attach($developers);
    $developerNote = Note::factory()->create(['title' => 'Docker layer cache misses']);
    $developerNote->teams()->attach($developers);
    $sysadminNote = Note::factory()->create(['title' => 'Docker daemon log rotation']);
    $sysadminNote->teams()->attach($sysadmins);

    $scoped = Note::searchScoped($developer, 'docker')->get();
    $broader = Note::searchScoped($developer, 'docker', broader: true)->get();

    expect($scoped->pluck('id')->all())->toEqualCanonicalizing([$developerNote->id]);
    expect($broader->pluck('id')->all())->toEqualCanonicalizing([$developerNote->id, $sysadminNote->id]);
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

    $note->assignTeams([]);
    expect($note->teams()->count())->toBe(0);
});
