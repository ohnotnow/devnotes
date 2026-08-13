<?php

use App\Models\User;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

it('turns away sso logins with no matching user', function () {
    Socialite::fake('keycloak', SocialiteUser::fake([
        'email' => 'stranger@example.test',
        'nickname' => 'stranger1x',
        'user' => ['family_name' => 'Stranger', 'given_name' => 'Sam'],
    ]));

    $response = $this->get(route('sso.callback'));

    $response->assertRedirect(route('not_allowed'));
    $this->assertGuest();
    expect(User::where('email', 'stranger@example.test')->exists())->toBeFalse();

    $friendlyPage = $this->get(route('not_allowed'));
    $friendlyPage->assertSuccessful();
    $friendlyPage->assertSee('ask one of the admins');
});

it('logs in a user whose email matches an existing account without overwriting their details', function () {
    $existingUser = User::factory()->create(['email' => 'member@example.test', 'username' => 'original9x']);
    Socialite::fake('keycloak', SocialiteUser::fake([
        'email' => 'Member@Example.Test',
        'nickname' => 'mb42x',
        'user' => ['family_name' => 'Member', 'given_name' => 'Mel'],
    ]));

    $response = $this->get(route('sso.callback'));

    $response->assertRedirect(route('home'));
    $this->assertAuthenticatedAs($existingUser);
    expect($existingUser->refresh()->username)->toBe('original9x');
});

it('backfills a stub user with their sso details on first login', function () {
    $stubUser = User::factory()->create([
        'email' => 'newstarter@example.test',
        'username' => '',
        'surname' => '',
        'forenames' => '',
        'is_staff' => true,
    ]);
    Socialite::fake('keycloak', SocialiteUser::fake([
        'email' => 'newstarter@example.test',
        'nickname' => '1234567a',
        'user' => ['family_name' => 'Starter', 'given_name' => 'Nadia'],
    ]));

    $this->get(route('sso.callback'))->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($stubUser);
    $stubUser->refresh();
    expect($stubUser->username)->toBe('1234567a');
    expect($stubUser->surname)->toBe('Starter');
    expect($stubUser->forenames)->toBe('Nadia');
    expect((bool) $stubUser->is_staff)->toBeFalse();
});

it('autocreates unknown users only when the config asks for it', function () {
    config(['sso.autocreate_new_users' => true]);
    Socialite::fake('keycloak', SocialiteUser::fake([
        'email' => 'autocreated@example.test',
        'nickname' => 'ac55x',
        'user' => ['family_name' => 'Created', 'given_name' => 'Auto'],
    ]));

    $this->get(route('sso.callback'))->assertRedirect(route('home'));

    $newUser = User::where('email', 'autocreated@example.test')->sole();
    $this->assertAuthenticatedAs($newUser);
    expect($newUser->username)->toBe('ac55x');
});

it('turns students away when the config forbids them', function () {
    config(['sso.allow_students' => false]);
    User::factory()->student()->create(['email' => 'student@example.test', 'username' => '7654321b']);
    Socialite::fake('keycloak', SocialiteUser::fake([
        'email' => 'student@example.test',
        'nickname' => '7654321b',
        'user' => ['family_name' => 'Student', 'given_name' => 'Sam'],
    ]));

    $this->get(route('sso.callback'))->assertForbidden();
    $this->assertGuest();
});

it('allows local login only when sso is disabled, with valid credentials', function () {
    $localUser = User::factory()->create(['username' => 'local1x', 'password' => bcrypt('secret')]);

    $ssoStillOn = $this->post(route('login.local'), ['username' => 'local1x', 'password' => 'secret']);
    $ssoStillOn->assertForbidden();
    $this->assertGuest();

    config(['sso.enabled' => false]);

    $wrongPassword = $this->post(route('login.local'), ['username' => 'local1x', 'password' => 'wrong']);
    $wrongPassword->assertSessionHasErrors(['username']);
    $this->assertGuest();

    $response = $this->post(route('login.local'), ['username' => 'local1x', 'password' => 'secret']);

    $response->assertRedirect(route('home'));
    $this->assertAuthenticatedAs($localUser);
});
