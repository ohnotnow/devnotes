<?php

use App\Livewire\Admin\Users;
use App\Models\Note;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

it('adds a stub user by email and rejects blanks and duplicates', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    User::factory()->create(['email' => 'taken@example.test']);

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->set('email', '  Fresh@Example.Test ')
        ->call('add')
        ->assertHasNoErrors();

    $stubUser = User::where('email', 'fresh@example.test')->sole();
    expect($stubUser->username)->toBe('');
    expect($stubUser->is_staff)->toBeTruthy();

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->set('email', '')
        ->call('add')
        ->assertHasErrors(['email']);

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->set('email', 'taken@example.test')
        ->call('add')
        ->assertHasErrors(['email']);

    expect(User::count())->toBe(3);
});

it('adds a user with their teams subscribed and set as note defaults', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $developers = Team::factory()->create(['name' => 'developers']);
    Team::factory()->create(['name' => 'sysadmins']);

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->set('email', 'newbie@example.test')
        ->set('selectedTeamIds', [$developers->id])
        ->call('add')
        ->assertHasNoErrors();

    $newUser = User::where('email', 'newbie@example.test')->sole();
    expect($newUser->subscribedTeams()->pluck('teams.id')->all())->toBe([$developers->id]);
    expect($newUser->defaultNoteTeams()->pluck('teams.id')->all())->toBe([$developers->id]);
    expect($newUser->teams()->pluck('teams.id')->all())->toBe([$developers->id]);
});

it('edits an existing user\'s teams from the table', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $developers = Team::factory()->create(['name' => 'developers']);
    $sysadmins = Team::factory()->create(['name' => 'sysadmins']);
    $mover = User::factory()->create();
    $mover->teams()->attach($developers);

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('openTeams', $mover->id)
        ->assertSet('selectedTeamIds', [$developers->id])
        ->set('selectedTeamIds', [$sysadmins->id])
        ->call('saveTeams')
        ->assertHasNoErrors();

    expect($mover->refresh()->subscribedTeams()->pluck('teams.id')->all())->toBe([$sysadmins->id]);
    expect($mover->defaultNoteTeams()->pluck('teams.id')->all())->toBe([$sysadmins->id]);
});

it('flattens a user\'s per-team fine-tuning when an admin saves their teams', function () {
    // Deliberate, documented in the flyout copy: admin membership is the
    // simple case - read and post to every ticked team.
    $admin = User::factory()->create(['is_admin' => true]);
    $developers = Team::factory()->create(['name' => 'developers']);
    $tuner = User::factory()->create();
    $tuner->teams()->attach($developers);
    $tuner->teams()->updateExistingPivot($developers->id, ['note_default' => false]);

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('openTeams', $tuner->id)
        ->call('saveTeams')
        ->assertHasNoErrors();

    expect($tuner->refresh()->defaultNoteTeams()->pluck('teams.id')->all())->toBe([$developers->id]);
});

it('does not leak a cancelled teams edit into the add-person form', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $developers = Team::factory()->create(['name' => 'developers']);
    $existingMember = User::factory()->create();
    $existingMember->teams()->attach($developers);

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('openTeams', $existingMember->id)
        ->assertSet('selectedTeamIds', [$developers->id])
        ->call('openAdd')
        ->assertSet('selectedTeamIds', [])
        ->set('email', 'newbie@example.test')
        ->call('add')
        ->assertHasNoErrors();

    expect(User::where('email', 'newbie@example.test')->sole()->teams)->toHaveCount(0);
});

it('toggles admin status both ways but never your own', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $colleague = User::factory()->create(['is_admin' => false]);
    $untouchedUser = User::factory()->create(['is_admin' => false]);

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('toggleAdmin', $colleague->id);

    expect($colleague->refresh()->is_admin)->toBeTruthy();

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('toggleAdmin', $colleague->id);

    expect($colleague->refresh()->is_admin)->toBeFalsy();
    expect($untouchedUser->refresh()->is_admin)->toBeFalsy();

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('toggleAdmin', $admin->id);

    expect($admin->refresh()->is_admin)->toBeTruthy();
});

it('keeps the leaver and their notes when the transfer choice is invalid', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $leaver = User::factory()->create();
    Note::factory()->create(['user_id' => $leaver->id]);

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('openDelete', $leaver->id)
        ->set('transferToId', (string) $leaver->id)
        ->call('deleteUser')
        ->assertHasErrors(['transferToId']);

    expect(User::find($leaver->id))->not->toBeNull();
    expect($leaver->notes()->count())->toBe(1);
});

it('deletes a user after transferring their notes to the chosen recipient', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $leaver = User::factory()->create();
    $recipient = User::factory()->create();
    $bystander = User::factory()->create();
    Note::factory(2)->create(['user_id' => $leaver->id]);
    Note::factory()->create(['user_id' => $bystander->id]);
    $trashedNote = Note::factory()->create(['user_id' => $leaver->id]);
    $trashedNote->delete();

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('openDelete', $leaver->id)
        ->assertSet('transferToId', (string) $admin->id)
        ->set('transferToId', (string) $recipient->id)
        ->call('deleteUser')
        ->assertHasNoErrors();

    expect(User::find($leaver->id))->toBeNull();
    expect($recipient->notes()->count())->toBe(2);
    expect($recipient->notes()->withTrashed()->count())->toBe(3);
    expect($bystander->notes()->count())->toBe(1);
    expect(Note::count())->toBe(3);
});

it('shows the users nav link to admins only', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $regularUser = User::factory()->create(['is_admin' => false]);

    $regularUserPage = $this->actingAs($regularUser)->get('/');
    $regularUserPage->assertSuccessful();
    $regularUserPage->assertSee(route('api-tokens'));
    $regularUserPage->assertDontSee(route('admin.users'));

    $this->actingAs($admin)->get('/')->assertSee(route('admin.users'));
});

it('refuses to delete your own account', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('openDelete', $admin->id)
        ->call('deleteUser');

    expect(User::find($admin->id))->not->toBeNull();
});

it('lets admins in and keeps everyone else out', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $regularUser = User::factory()->create(['is_admin' => false]);

    $this->get(route('admin.users'))->assertRedirect(route('login'));
    $this->actingAs($regularUser)->get(route('admin.users'))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.users'))->assertSuccessful();
});
