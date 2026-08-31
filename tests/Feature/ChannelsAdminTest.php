<?php

use App\Livewire\Admin\Channels;
use App\Models\Channel;
use App\Models\Note;
use App\Models\User;
use Livewire\Livewire;

it('shows admins the channel list with subscriber and note counts, and turns everyone else away', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $regularUser = User::factory()->create(['is_admin' => false]);
    $developers = Channel::factory()->create(['name' => 'developers']);
    $developers->users()->attach($admin);
    $developers->users()->attach($regularUser, ['subscribed' => false, 'note_default' => true]);
    Note::factory(3)->create()->each(fn (Note $note) => $note->channels()->attach($developers));
    Note::factory()->create()->channels()->attach($developers);
    Note::first()->delete();

    // The guest check comes first: actingAs persists for the rest of the test,
    // so a later "guest" request is still signed in as whoever acted last.
    $this->get(route('admin.channels'))->assertRedirect(route('login'));

    $this->actingAs($admin)->get(route('admin.channels'))
        ->assertSuccessful()
        ->assertSee('developers');

    // The counts render as bare digits, which match all sorts of markup, so
    // assert the numbers the view was handed. A soft-deleted note is not
    // counted, and neither is someone attached to the channel without a
    // subscription (note_default only) - the column is about subscribers.
    Livewire::actingAs($admin)
        ->test(Channels::class)
        ->assertViewHas('channels', fn ($channels) => $channels->sole()->subscribers_count === 1 && $channels->sole()->notes_count === 3);

    $this->actingAs($regularUser)->get(route('admin.channels'))->assertForbidden();
});

it('creates and renames channels, rejecting duplicate, blank, and oversized names', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Channel::factory()->create(['name' => 'taken']);

    Livewire::actingAs($admin)
        ->test(Channels::class)
        ->call('openCreate')
        ->set('editing.name', 'developers')
        ->call('save')
        ->assertHasNoErrors();
    $channel = Channel::where('name', 'developers')->sole();

    // Saving without changing the name must pass - the unique rule ignores
    // the channel's own row.
    Livewire::actingAs($admin)
        ->test(Channels::class)
        ->call('openEdit', $channel->id)
        ->assertSet('editing.name', 'developers')
        ->call('save')
        ->assertHasNoErrors();

    Livewire::actingAs($admin)
        ->test(Channels::class)
        ->call('openEdit', $channel->id)
        ->set('editing.name', 'platform')
        ->call('save')
        ->assertHasNoErrors();
    expect($channel->refresh()->name)->toBe('platform');

    Livewire::actingAs($admin)
        ->test(Channels::class)
        ->call('openEdit', $channel->id)
        ->set('editing.name', 'taken')
        ->call('save')
        ->assertHasErrors(['editing.name']);
    expect($channel->refresh()->name)->toBe('platform');

    Livewire::actingAs($admin)
        ->test(Channels::class)
        ->call('openCreate')
        ->set('editing.name', '')
        ->call('save')
        ->assertHasErrors(['editing.name']);

    Livewire::actingAs($admin)
        ->test(Channels::class)
        ->call('openCreate')
        ->set('editing.name', str_repeat('x', 256))
        ->call('save')
        ->assertHasErrors(['editing.name']);

    expect(Channel::count())->toBe(2);
});

it('deletes a channel without hiding or losing anything', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $doomed = Channel::factory()->create(['name' => 'doomed']);
    $survivor = Channel::factory()->create(['name' => 'survivor']);
    $member = User::factory()->create();
    $member->channels()->attach([$doomed->id, $survivor->id]);
    $note = Note::factory()->create(['title' => 'Docker daemon log rotation']);
    $note->channels()->attach($doomed);
    $controlNote = Note::factory()->create(['title' => 'Docker registry mirror config']);
    $controlNote->channels()->attach($survivor);

    Livewire::actingAs($admin)
        ->test(Channels::class)
        ->call('openDelete', $doomed->id)
        ->call('deleteChannel')
        ->assertHasNoErrors();

    expect(Channel::where('name', 'doomed')->exists())->toBeFalse();
    expect($member->refresh()->channels()->pluck('channels.id')->all())->toBe([$survivor->id]);
    expect($note->refresh()->channels)->toHaveCount(0);
    expect($controlNote->refresh()->channels()->pluck('channels.id')->all())->toBe([$survivor->id]);
    expect(Note::searchScoped($member, 'docker')->get()->pluck('id')->all())->toEqualCanonicalizing([$note->id, $controlNote->id]);
});
