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

it('lets students in when the config allows them', function () {
    $studentUser = User::factory()->student()->create(['email' => 'student@example.test', 'username' => '7654321b']);
    Socialite::fake('keycloak', SocialiteUser::fake([
        'email' => 'student@example.test',
        'nickname' => '7654321b',
        'user' => ['family_name' => 'Student', 'given_name' => 'Sam'],
    ]));

    $this->get(route('sso.callback'))->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($studentUser);
});

it('turns a student away before autocreating them, however keen the config is', function () {
    config(['sso.allow_students' => false, 'sso.autocreate_new_users' => true]);
    Socialite::fake('keycloak', SocialiteUser::fake([
        'email' => 'newstudent@example.test',
        'nickname' => '7654321b',
        'user' => ['family_name' => 'Student', 'given_name' => 'Sam'],
    ]));

    $this->get(route('sso.callback'))->assertForbidden();

    $this->assertGuest();
    expect(User::where('email', 'newstudent@example.test')->exists())->toBeFalse();
});

it('lets only admins in when the config says admins only', function () {
    config(['sso.admins_only' => true]);
    $adminUser = User::factory()->admin()->create(['email' => 'admin@example.test', 'username' => 'ad11x']);
    User::factory()->create(['email' => 'staff@example.test', 'username' => 'st22x']);

    Socialite::fake('keycloak', SocialiteUser::fake([
        'email' => 'staff@example.test',
        'nickname' => 'st22x',
        'user' => ['family_name' => 'Staffer', 'given_name' => 'Sal'],
    ]));
    $this->get(route('sso.callback'))->assertForbidden();
    $this->assertGuest();

    Socialite::fake('keycloak', SocialiteUser::fake([
        'email' => 'admin@example.test',
        'nickname' => 'ad11x',
        'user' => ['family_name' => 'Admin', 'given_name' => 'Ada'],
    ]));
    $this->get(route('sso.callback'))->assertRedirect(route('home'));
    $this->assertAuthenticatedAs($adminUser);
});

it('turns an unknown user away under admins-only without creating them', function () {
    config(['sso.admins_only' => true, 'sso.autocreate_new_users' => true]);
    Socialite::fake('keycloak', SocialiteUser::fake([
        'email' => 'stranger@example.test',
        'nickname' => 'sx99x',
        'user' => ['family_name' => 'Stranger', 'given_name' => 'Sam'],
    ]));

    $this->get(route('sso.callback'))->assertForbidden();

    $this->assertGuest();
    expect(User::where('email', 'stranger@example.test')->exists())->toBeFalse();
});

it('refuses a login when the sso provider itself fails', function () {
    Socialite::shouldReceive('driver->user')->andThrow(new RuntimeException('token exchange failed'));

    $this->get(route('sso.callback'))->assertForbidden();

    $this->assertGuest();
    expect(User::count())->toBe(0);
});

it('throttles repeated local login attempts for the same username', function () {
    config(['sso.enabled' => false]);
    User::factory()->create(['username' => 'local1x', 'password' => bcrypt('secret')]);

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login.local'), ['username' => 'local1x', 'password' => 'wrong'])
            ->assertSessionHasErrors(['username']);
    }

    // The right password is now refused too - the throttle is on the username,
    // not on the wrongness of the guess.
    $throttled = $this->post(route('login.local'), ['username' => 'local1x', 'password' => 'secret']);
    $throttled->assertSessionHasErrors(['username']);
    $this->assertGuest();
    expect(session('errors')->first('username'))->toContain('Too many login attempts');

    // A different username is unaffected.
    $otherUser = User::factory()->create(['username' => 'local2x', 'password' => bcrypt('secret')]);
    $this->post(route('login.local'), ['username' => 'local2x', 'password' => 'secret'])
        ->assertRedirect(route('home'));
    $this->assertAuthenticatedAs($otherUser);
});
