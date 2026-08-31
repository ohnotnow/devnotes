<?php

use App\Livewire\ChannelSettings;
use App\Models\Channel;
use App\Models\Note;
use App\Models\User;
use Livewire\Livewire;

it('shows the user\'s current subscriptions and note defaults pre-checked', function () {
    $developers = Channel::factory()->create(['name' => 'developers']);
    $security = Channel::factory()->create(['name' => 'security']);
    $member = User::factory()->create();
    $member->channels()->attach([$developers->id, $security->id]);
    $member->channels()->updateExistingPivot($security->id, ['note_default' => false]);

    Livewire::actingAs($member)
        ->test(ChannelSettings::class)
        ->assertSee('developers')
        ->assertSee('security')
        ->assertSet('subscribedChannelIds', [$developers->id, $security->id])
        ->assertSet('defaultChannelIds', [$developers->id]);
});

it('saves diverging subscriptions and note defaults, dropping channels unchecked in both', function () {
    $developers = Channel::factory()->create(['name' => 'developers']);
    $security = Channel::factory()->create(['name' => 'security']);
    $lapsed = Channel::factory()->create(['name' => 'lapsed']);
    $writeOnly = Channel::factory()->create(['name' => 'write-only']);
    $member = User::factory()->create();
    $member->channels()->attach([$developers->id, $lapsed->id]);

    Livewire::actingAs($member)
        ->test(ChannelSettings::class)
        ->set('subscribedChannelIds', [$developers->id, $security->id])
        ->set('defaultChannelIds', [$developers->id, $writeOnly->id])
        ->call('save')
        ->assertHasNoErrors();

    $member->refresh();
    expect($member->subscribedChannels()->pluck('channels.id')->all())->toEqualCanonicalizing([$developers->id, $security->id]);
    expect($member->defaultNoteChannels()->pluck('channels.id')->all())->toEqualCanonicalizing([$developers->id, $writeOnly->id]);
    expect($member->channels()->pluck('channels.id')->all())->toEqualCanonicalizing([$developers->id, $security->id, $writeOnly->id]);
});

it('rejects a channel id that does not exist without saving anything', function () {
    $developers = Channel::factory()->create(['name' => 'developers']);
    $member = User::factory()->create();
    $member->channels()->attach($developers);

    Livewire::actingAs($member)
        ->test(ChannelSettings::class)
        ->set('subscribedChannelIds', [999])
        ->call('save')
        ->assertHasErrors(['subscribedChannelIds.0']);

    Livewire::actingAs($member)
        ->test(ChannelSettings::class)
        ->set('defaultChannelIds', [999])
        ->call('save')
        ->assertHasErrors(['defaultChannelIds.0']);

    expect($member->refresh()->channels()->pluck('channels.id')->all())->toBe([$developers->id]);
});

it('changes what scoped search shows once saved', function () {
    $developers = Channel::factory()->create(['name' => 'developers']);
    $sysadmins = Channel::factory()->create(['name' => 'sysadmins']);
    $member = User::factory()->create();
    $member->channels()->attach([$developers->id, $sysadmins->id]);
    $sysadminNote = Note::factory()->create(['title' => 'Docker daemon log rotation']);
    $sysadminNote->channels()->attach($sysadmins);

    expect(Note::searchScoped($member->fresh(), 'docker')->get()->pluck('id')->all())->toBe([$sysadminNote->id]);

    Livewire::actingAs($member)
        ->test(ChannelSettings::class)
        ->set('subscribedChannelIds', [$developers->id])
        ->set('defaultChannelIds', [$developers->id])
        ->call('save')
        ->assertHasNoErrors();

    expect(Note::searchScoped($member->fresh(), 'docker')->get())->toHaveCount(0);
});

it('renders the full page for a logged-in user and requires login otherwise', function () {
    $member = User::factory()->create();

    $this->get(route('channel-settings'))->assertRedirect(route('login'));

    $this->actingAs($member)->get(route('channel-settings'))->assertSuccessful();
});
