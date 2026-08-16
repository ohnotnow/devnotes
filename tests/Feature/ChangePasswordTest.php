<?php

use App\Livewire\Admin\Users;
use App\Livewire\ChangePassword;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

it('lets a user set a new password with their current one', function () {
    config(['sso.enabled' => false]);
    $flaggedUser = User::factory()->mustChangePassword()->create([
        'username' => 'changer1x',
        'password' => bcrypt('temporary-pass'),
    ]);

    Livewire::actingAs($flaggedUser)
        ->test(ChangePassword::class)
        ->set('currentPassword', 'temporary-pass')
        ->set('newPassword', 'my-own-password')
        ->set('newPassword_confirmation', 'my-own-password')
        ->call('save')
        ->assertHasNoErrors();

    expect($flaggedUser->refresh()->must_change_password)->toBeFalse();

    Auth::logout();
    $this->post(route('login.local'), ['username' => 'changer1x', 'password' => 'my-own-password'])
        ->assertRedirect(route('home'));
    $this->assertAuthenticatedAs($flaggedUser);
});

it('offers the change password page only when sso is off', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('change-password'))->assertNotFound();
    $this->actingAs($user)->get('/')->assertDontSee(route('change-password'));

    config(['sso.enabled' => false]);

    $this->actingAs($user)->get(route('change-password'))->assertSuccessful();
    $this->actingAs($user)->get('/')->assertSee(route('change-password'));
});

it('herds a flagged user to the change password screen when sso is off', function () {
    config(['sso.enabled' => false]);
    $flaggedUser = User::factory()->mustChangePassword()->create();

    $this->actingAs($flaggedUser)->get(route('home'))->assertRedirect(route('change-password'));
    $this->actingAs($flaggedUser)->get(route('change-password'))->assertSuccessful();

    $this->actingAs($flaggedUser)->post(route('auth.logout'))->assertRedirect(route('logged_out'));
    $this->assertGuest();
});

it('leaves unflagged users and sso installs alone', function () {
    config(['sso.enabled' => false]);
    $unflaggedUser = User::factory()->create();
    $this->actingAs($unflaggedUser)->get(route('home'))->assertSuccessful();

    config(['sso.enabled' => true]);
    $lingeringFlagUser = User::factory()->mustChangePassword()->create();
    $this->actingAs($lingeringFlagUser)->get(route('home'))->assertSuccessful();
});

it('walks a new person from admin add to their own password', function () {
    config(['sso.enabled' => false]);
    $admin = User::factory()->create(['is_admin' => true]);

    $temporaryPassword = Livewire::actingAs($admin)
        ->test(Users::class)
        ->set('email', 'newbie@example.test')
        ->set('username', 'newbie1x')
        ->call('save')
        ->get('newTempPassword');

    Auth::logout();
    $this->post(route('login.local'), ['username' => 'newbie1x', 'password' => $temporaryPassword]);

    $newUser = User::where('username', 'newbie1x')->sole();
    $this->assertAuthenticatedAs($newUser);
    $this->get(route('home'))->assertRedirect(route('change-password'));

    Livewire::actingAs($newUser)
        ->test(ChangePassword::class)
        ->set('currentPassword', $temporaryPassword)
        ->set('newPassword', 'my-own-password')
        ->set('newPassword_confirmation', 'my-own-password')
        ->call('save')
        ->assertHasNoErrors();

    $this->actingAs($newUser->refresh())->get(route('home'))->assertSuccessful();
});

it('rejects a wrong current password or a fumbled confirmation', function () {
    config(['sso.enabled' => false]);
    $flaggedUser = User::factory()->mustChangePassword()->create([
        'username' => 'changer1x',
        'password' => bcrypt('temporary-pass'),
    ]);

    Livewire::actingAs($flaggedUser)
        ->test(ChangePassword::class)
        ->set('currentPassword', 'not-my-password')
        ->set('newPassword', 'my-own-password')
        ->set('newPassword_confirmation', 'my-own-password')
        ->call('save')
        ->assertHasErrors(['currentPassword']);

    Livewire::actingAs($flaggedUser)
        ->test(ChangePassword::class)
        ->set('currentPassword', 'temporary-pass')
        ->set('newPassword', 'my-own-password')
        ->set('newPassword_confirmation', 'my-own-passwrd')
        ->call('save')
        ->assertHasErrors(['newPassword']);

    expect($flaggedUser->refresh()->must_change_password)->toBeTrue();

    Auth::logout();
    $this->post(route('login.local'), ['username' => 'changer1x', 'password' => 'temporary-pass'])
        ->assertRedirect(route('home'));
    $this->assertAuthenticatedAs($flaggedUser);
});
