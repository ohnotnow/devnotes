<?php

use App\Models\Channel;
use App\Models\Note;
use App\Models\User;

it('scopes search to subscribed channels only', function () {
    $developers = Channel::factory()->create(['name' => 'developers']);
    $frontend = Channel::factory()->create(['name' => 'frontend']);
    $sysadmins = Channel::factory()->create(['name' => 'sysadmins']);
    $lapsed = Channel::factory()->create(['name' => 'lapsed']);
    $developer = User::factory()->create();
    $developer->channels()->attach([$developers->id, $frontend->id, $lapsed->id]);
    $developer->channels()->updateExistingPivot($lapsed->id, ['subscribed' => false]);
    $developerNote = Note::factory()->create(['title' => 'Docker layer cache misses']);
    $developerNote->channels()->attach($developers);
    $frontendNote = Note::factory()->create(['title' => 'Docker vite dev-server networking']);
    $frontendNote->channels()->attach($frontend);
    $sysadminNote = Note::factory()->create(['title' => 'Docker daemon log rotation']);
    $sysadminNote->channels()->attach($sysadmins);
    $lapsedNote = Note::factory()->create(['title' => 'Docker swarm leftovers']);
    $lapsedNote->channels()->attach($lapsed);

    $results = Note::searchScoped($developer, 'docker')->get();

    expect($results->pluck('id')->all())->toEqualCanonicalizing([$developerNote->id, $frontendNote->id]);
});

it('shows channel-less notes in a scoped search that excludes other channels', function () {
    $developers = Channel::factory()->create(['name' => 'developers']);
    $sysadmins = Channel::factory()->create(['name' => 'sysadmins']);
    $developer = User::factory()->create();
    $developer->channels()->attach($developers);
    $channellessNote = Note::factory()->create(['title' => 'Docker compose healthcheck gotcha']);
    $sysadminNote = Note::factory()->create(['title' => 'Docker daemon log rotation']);
    $sysadminNote->channels()->attach($sysadmins);

    $results = Note::searchScoped($developer, 'docker')->get();

    expect($results->pluck('id')->all())->toEqualCanonicalizing([$channellessNote->id]);
});

it('searches the whole pot for a user with no subscriptions', function () {
    $sysadmins = Channel::factory()->create(['name' => 'sysadmins']);
    $userWithoutChannels = User::factory()->create();
    $sysadminNote = Note::factory()->create(['title' => 'Docker daemon log rotation']);
    $sysadminNote->channels()->attach($sysadmins);

    $results = Note::searchScoped($userWithoutChannels, 'docker')->get();

    expect($results->pluck('id')->all())->toEqualCanonicalizing([$sysadminNote->id]);
});

it('returns cross-channel matches when broader', function () {
    $developers = Channel::factory()->create(['name' => 'developers']);
    $sysadmins = Channel::factory()->create(['name' => 'sysadmins']);
    $developer = User::factory()->create();
    $developer->channels()->attach($developers);
    $developerNote = Note::factory()->create(['title' => 'Docker layer cache misses']);
    $developerNote->channels()->attach($developers);
    $sysadminNote = Note::factory()->create(['title' => 'Docker daemon log rotation']);
    $sysadminNote->channels()->attach($sysadmins);

    $scoped = Note::searchScoped($developer, 'docker')->get();
    $broader = Note::searchScoped($developer, 'docker', broader: true)->get();

    expect($scoped->pluck('id')->all())->toEqualCanonicalizing([$developerNote->id]);
    expect($broader->pluck('id')->all())->toEqualCanonicalizing([$developerNote->id, $sysadminNote->id]);
});

it('defaults a note\'s channels to the author\'s note defaults', function () {
    $developers = Channel::factory()->create(['name' => 'developers']);
    $security = Channel::factory()->create(['name' => 'security']);
    $author = User::factory()->create();
    $author->channels()->attach([$developers->id, $security->id]);
    $author->channels()->updateExistingPivot($security->id, ['note_default' => false]);
    $note = Note::factory()->create(['user_id' => $author->id]);

    $note->assignChannels();

    expect($note->channels()->pluck('channels.id')->all())->toBe([$developers->id]);
});

it('assigns exactly the explicit channels when given', function () {
    $developers = Channel::factory()->create(['name' => 'developers']);
    $sysadmins = Channel::factory()->create(['name' => 'sysadmins']);
    $author = User::factory()->create();
    $author->channels()->attach($developers);
    $note = Note::factory()->create(['user_id' => $author->id]);

    $note->assignChannels([$sysadmins->id]);
    expect($note->channels()->pluck('channels.id')->all())->toBe([$sysadmins->id]);

    $note->assignChannels([]);
    expect($note->channels()->count())->toBe(0);
});
