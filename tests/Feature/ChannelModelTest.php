<?php

use App\Models\Channel;
use App\Models\Note;
use App\Models\User;
use Illuminate\Database\QueryException;

it('creates a channel with a unique name', function () {
    Channel::create(['name' => 'developers']);

    expect(Channel::sole()->name)->toBe('developers');

    expect(fn () => Channel::create(['name' => 'developers']))->toThrow(QueryException::class);
    expect(Channel::count())->toBe(1);
});

it('subscribes a member to a channel and defaults their notes there', function () {
    $channel = Channel::factory()->create();
    $member = User::factory()->create();
    $nonMember = User::factory()->create();

    $member->channels()->attach($channel);

    expect($member->subscribedChannels->sole()->is($channel))->toBeTrue();
    expect($member->defaultNoteChannels->sole()->is($channel))->toBeTrue();
    expect($nonMember->subscribedChannels)->toHaveCount(0);
    expect($channel->users->sole()->is($member))->toBeTrue();
});

it('lets subscriptions and note defaults diverge independently', function () {
    $channel = Channel::factory()->create();
    $member = User::factory()->create();
    $member->channels()->attach($channel);

    $member->channels()->updateExistingPivot($channel->id, ['subscribed' => false]);

    expect($member->subscribedChannels)->toHaveCount(0);
    expect($member->defaultNoteChannels->sole()->is($channel))->toBeTrue();
});

it('gives consecutive channels different badge colours', function () {
    $channels = Channel::factory()->count(5)->create();

    $colours = $channels->map(fn (Channel $channel) => $channel->colour());

    expect($colours->unique())->toHaveCount(5);
});

it('lets a note carry several channels', function () {
    $developers = Channel::factory()->create(['name' => 'developers']);
    $sysadmins = Channel::factory()->create(['name' => 'sysadmins']);
    $taggedNote = Note::factory()->create();
    $channellessNote = Note::factory()->create();

    $taggedNote->channels()->attach([$developers->id, $sysadmins->id]);

    expect($taggedNote->channels)->toHaveCount(2);
    expect($developers->notes->sole()->is($taggedNote))->toBeTrue();
    expect($channellessNote->channels)->toHaveCount(0);
});
