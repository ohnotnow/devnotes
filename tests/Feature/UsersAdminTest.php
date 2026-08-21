<?php

use App\Enums\ActivityAction;
use App\Livewire\Admin\Users;
use App\Models\Activity;
use App\Models\Note;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

it('adds a stub user by email and rejects blanks and duplicates', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    User::factory()->create(['email' => 'taken@example.test']);

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->set('email', '  Fresh@Example.Test ')
        ->call('save')
        ->assertHasNoErrors();

    $stubUser = User::where('email', 'fresh@example.test')->sole();
    expect($stubUser->username)->toBe('');
    expect($stubUser->is_staff)->toBeTruthy();

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->set('email', '')
        ->call('save')
        ->assertHasErrors(['email']);

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->set('email', 'taken@example.test')
        ->call('save')
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
        ->call('save')
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
        ->call('openEdit', $mover->id)
        ->assertSet('selectedTeamIds', [$developers->id])
        ->set('selectedTeamIds', [$sysadmins->id])
        ->call('save')
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
        ->call('openEdit', $tuner->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($tuner->refresh()->defaultNoteTeams()->pluck('teams.id')->all())->toBe([$developers->id]);
});

it('does not leak a cancelled edit into the add-person form', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $developers = Team::factory()->create(['name' => 'developers']);
    $existingMember = User::factory()->create();
    $existingMember->teams()->attach($developers);

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('openEdit', $existingMember->id)
        ->assertSet('selectedTeamIds', [$developers->id])
        ->call('openAdd')
        ->assertSet('selectedTeamIds', [])
        ->assertSet('email', '')
        ->set('email', 'newbie@example.test')
        ->call('save')
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

    expect($colleague->refresh()->is_admin)->toBeTrue();

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('toggleAdmin', $colleague->id);

    expect($colleague->refresh()->is_admin)->toBeFalse();
    expect($untouchedUser->refresh()->is_admin)->toBeFalse();

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('toggleAdmin', $admin->id);

    expect($admin->refresh()->is_admin)->toBeTrue();
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
    // The transfer is a bulk update, so it must not look like the admin edited
    // every note they inherited.
    expect(Activity::count())->toBe(0);
});

it('keeps a deleted users activity rows and shows them as a deleted user', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $leaver = User::factory()->create();
    $note = Note::factory()->create(['user_id' => $leaver->id]);
    Activity::factory()->create([
        'user_id' => $leaver->id,
        'note_id' => $note->id,
        'action' => ActivityAction::Created,
        'description' => "created note #{$note->code} '{$note->title}'",
    ]);

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('openDelete', $leaver->id)
        ->set('transferToId', (string) $admin->id)
        ->call('deleteUser')
        ->assertHasNoErrors();

    expect(Activity::count())->toBe(1);
    expect(Activity::sole()->user_id)->toBeNull();

    $this->actingAs($admin)->get(route('admin.activity'))
        ->assertSuccessful()
        ->assertSee('Deleted user')
        ->assertSee("created note #{$note->code}");
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

it('creates a working login with a revealed temporary password when sso is off', function () {
    config(['sso.enabled' => false]);
    $admin = User::factory()->create(['is_admin' => true]);

    $component = Livewire::actingAs($admin)
        ->test(Users::class)
        ->set('email', 'newbie@example.test')
        ->set('username', 'newbie1x')
        ->set('forenames', 'New')
        ->set('surname', 'Person')
        ->call('save')
        ->assertHasNoErrors();

    $newUser = User::where('email', 'newbie@example.test')->sole();
    expect($newUser->username)->toBe('newbie1x');
    expect($newUser->full_name)->toBe('New Person');
    expect($newUser->must_change_password)->toBeTrue();

    $temporaryPassword = $component->get('newTempPassword');
    expect($temporaryPassword)->not->toBe('');

    Auth::logout();
    $this->post(route('login.local'), ['username' => 'newbie1x', 'password' => $temporaryPassword])
        ->assertRedirect(route('home'));
    $this->assertAuthenticatedAs($newUser);
});

it('surfaces the temporary password for copying after a non-sso add', function () {
    config(['sso.enabled' => false]);
    $admin = User::factory()->create(['is_admin' => true]);

    $component = Livewire::actingAs($admin)
        ->test(Users::class)
        ->set('email', 'newbie@example.test')
        ->set('username', 'newbie1x')
        ->call('save')
        ->assertHasNoErrors();

    $temporaryPassword = $component->get('newTempPassword');
    expect($temporaryPassword)->not->toBeEmpty();
    $component->assertSee($temporaryPassword);

    // Blade directives are NOT compiled inside component tag attributes, so a
    // literal @js( reaching the browser means the copy button's Alpine
    // expression is broken (illegal character U+0040).
    $component->assertDontSee('@js(');
});

it('requires a unique username to add someone when sso is off', function () {
    config(['sso.enabled' => false]);
    $admin = User::factory()->create(['is_admin' => true]);
    User::factory()->create(['username' => 'taken1x']);

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->set('email', 'newbie@example.test')
        ->set('username', '')
        ->call('save')
        ->assertHasErrors(['username']);

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->set('email', 'newbie@example.test')
        ->set('username', 'taken1x')
        ->call('save')
        ->assertHasErrors(['username']);

    expect(User::where('email', 'newbie@example.test')->exists())->toBeFalse();
    expect(User::count())->toBe(2);
});

it('edits a person\'s details and teams from the flyout when sso is off', function () {
    config(['sso.enabled' => false]);
    $admin = User::factory()->create(['is_admin' => true]);
    $developers = Team::factory()->create(['name' => 'developers']);
    $fatFingered = User::factory()->create([
        'email' => 'wrong@example.test',
        'username' => 'wrong1x',
        'forenames' => 'Wrng',
        'surname' => 'Persn',
    ]);

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('openEdit', $fatFingered->id)
        ->set('email', 'right@example.test')
        ->set('username', 'right1x')
        ->set('forenames', 'Right')
        ->set('surname', 'Person')
        ->set('selectedTeamIds', [$developers->id])
        ->call('save')
        ->assertHasNoErrors();

    $fatFingered->refresh();
    expect($fatFingered->email)->toBe('right@example.test');
    expect($fatFingered->username)->toBe('right1x');
    expect($fatFingered->full_name)->toBe('Right Person');
    expect($fatFingered->teams()->pluck('teams.id')->all())->toBe([$developers->id]);
    expect(User::count())->toBe(2);
});

it('lets an sso-mode edit change teams but never identity details', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $developers = Team::factory()->create(['name' => 'developers']);
    $ssoOwned = User::factory()->create([
        'email' => 'sso@example.test',
        'username' => 'sso1x',
        'forenames' => 'Sso',
        'surname' => 'Person',
    ]);

    // A crafted request can set any public property - the disabled fields in
    // the UI are presentation, this guards the server side.
    Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('openEdit', $ssoOwned->id)
        ->set('email', 'hijacked@example.test')
        ->set('username', 'hijacked1x')
        ->set('forenames', 'Hi')
        ->set('surname', 'Jacked')
        ->set('selectedTeamIds', [$developers->id])
        ->call('save')
        ->assertHasNoErrors();

    $ssoOwned->refresh();
    expect($ssoOwned->email)->toBe('sso@example.test');
    expect($ssoOwned->username)->toBe('sso1x');
    expect($ssoOwned->full_name)->toBe('Sso Person');
    expect($ssoOwned->teams()->pluck('teams.id')->all())->toBe([$developers->id]);
});

it('regenerates a working temporary password only when sso is off', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $forgetful = User::factory()->create(['username' => 'forgetful1x', 'password' => bcrypt('old-password')]);

    $ssoStillOn = Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('openEdit', $forgetful->id)
        ->call('regeneratePassword');
    expect($ssoStillOn->get('newTempPassword'))->toBe('');
    expect($forgetful->refresh()->must_change_password)->toBeFalse();

    config(['sso.enabled' => false]);

    $component = Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('openEdit', $forgetful->id)
        ->call('regeneratePassword')
        ->assertHasNoErrors();

    $temporaryPassword = $component->get('newTempPassword');
    expect($temporaryPassword)->not->toBeEmpty();
    expect($forgetful->refresh()->must_change_password)->toBeTrue();

    Auth::logout();
    $this->post(route('login.local'), ['username' => 'forgetful1x', 'password' => 'old-password'])
        ->assertSessionHasErrors(['username']);
    $this->assertGuest();

    $this->post(route('login.local'), ['username' => 'forgetful1x', 'password' => $temporaryPassword])
        ->assertRedirect(route('home'));
    $this->assertAuthenticatedAs($forgetful);
});

it('keeps the sso add flow free of temporary passwords', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $component = Livewire::actingAs($admin)
        ->test(Users::class)
        ->set('email', 'newbie@example.test')
        ->call('save')
        ->assertHasNoErrors();

    $stubUser = User::where('email', 'newbie@example.test')->sole();
    expect($stubUser->must_change_password)->toBeFalse();
    expect($component->get('newTempPassword'))->toBe('');
});
