<?php

use App\Models\Note;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\TestDataSeeder;
use Illuminate\Database\QueryException;

it('creates a team with a unique name', function () {
    Team::create(['name' => 'developers']);

    expect(Team::sole()->name)->toBe('developers');

    expect(fn () => Team::create(['name' => 'developers']))->toThrow(QueryException::class);
    expect(Team::count())->toBe(1);
});

it('subscribes a member to a team and defaults their notes there', function () {
    $team = Team::factory()->create();
    $member = User::factory()->create();
    $nonMember = User::factory()->create();

    $member->teams()->attach($team);

    expect($member->subscribedTeams->sole()->is($team))->toBeTrue();
    expect($member->defaultNoteTeams->sole()->is($team))->toBeTrue();
    expect($nonMember->subscribedTeams)->toHaveCount(0);
    expect($team->users->sole()->is($member))->toBeTrue();
});

it('lets subscriptions and note defaults diverge independently', function () {
    $team = Team::factory()->create();
    $member = User::factory()->create();
    $member->teams()->attach($team);

    $member->teams()->updateExistingPivot($team->id, ['subscribed' => false]);

    expect($member->subscribedTeams)->toHaveCount(0);
    expect($member->defaultNoteTeams->sole()->is($team))->toBeTrue();
});

it('lets a note carry several teams', function () {
    $developers = Team::factory()->create(['name' => 'developers']);
    $sysadmins = Team::factory()->create(['name' => 'sysadmins']);
    $taggedNote = Note::factory()->create();
    $teamlessNote = Note::factory()->create();

    $taggedNote->teams()->attach([$developers->id, $sysadmins->id]);

    expect($taggedNote->teams)->toHaveCount(2);
    expect($developers->notes->sole()->is($taggedNote))->toBeTrue();
    expect($teamlessNote->teams)->toHaveCount(0);
});

it('seeds teams, memberships, and the docker demo pair', function () {
    $this->seed(TestDataSeeder::class);

    $developers = Team::where('name', 'developers')->sole();
    $sysadmins = Team::where('name', 'sysadmins')->sole();

    $adminUser = User::where('username', 'admin2x')->sole();
    $standardUser = User::where('username', 'user2x')->sole();
    expect($adminUser->subscribedTeams->sole()->is($developers))->toBeTrue();
    expect($adminUser->defaultNoteTeams->sole()->is($developers))->toBeTrue();
    expect($standardUser->subscribedTeams->sole()->is($sysadmins))->toBeTrue();
    expect($standardUser->defaultNoteTeams->sole()->is($sysadmins))->toBeTrue();

    $developerDockerNote = Note::where('title', 'Docker layer cache misses on multi-stage builds')->sole();
    $sysadminDockerNote = Note::where('title', 'Docker daemon log rotation filling /var on VM hosts')->sole();
    expect($developerDockerNote->teams->sole()->is($developers))->toBeTrue();
    expect($sysadminDockerNote->teams->sole()->is($sysadmins))->toBeTrue();

    expect($developers->notes)->toHaveCount(3);
    expect(Note::whereDoesntHave('teams')->count())->toBe(3);
});
