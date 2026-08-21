<?php

use App\Livewire\ApiTokens;
use App\Models\User;
use Livewire\Livewire;

it('shows the token screen to logged-in users only', function () {
    $user = User::factory()->create();

    $this->get(route('api-tokens'))->assertRedirect(route('login'));
    $this->actingAs($user)->get(route('api-tokens'))->assertSuccessful();
});

it('rejects a blank token name and creates nothing', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ApiTokens::class)
        ->set('tokenName', '')
        ->call('create')
        ->assertHasErrors(['tokenName']);

    Livewire::actingAs($user)
        ->test(ApiTokens::class)
        ->set('tokenName', str_repeat('x', 256))
        ->call('create')
        ->assertHasErrors(['tokenName']);

    expect($user->tokens()->count())->toBe(0);
});

it('revokes exactly one token', function () {
    $user = User::factory()->create();
    $tokenToRevoke = $user->createToken('old laptop')->accessToken;
    $tokenToKeep = $user->createToken('new laptop')->accessToken;

    Livewire::actingAs($user)
        ->test(ApiTokens::class)
        ->call('revoke', $tokenToRevoke->id);

    expect($user->tokens()->pluck('id')->all())->toBe([$tokenToKeep->id]);
});

it('only ever shows and touches your own tokens', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherUsersToken = $otherUser->createToken('someone elses token')->accessToken;

    Livewire::actingAs($user)
        ->test(ApiTokens::class)
        ->assertDontSee('someone elses token')
        ->call('revoke', $otherUsersToken->id);

    expect($otherUser->tokens()->count())->toBe(1);
});

it('shows every token in the system to an admin who turns on show-all', function () {
    $admin = User::factory()->admin()->create();
    $otherUser = User::factory()->create();
    $otherUser->createToken('forgotten laptop token');

    Livewire::actingAs($admin)
        ->test(ApiTokens::class)
        ->assertDontSee('forgotten laptop token')
        ->set('showAll', true)
        ->assertSee('forgotten laptop token')
        ->assertSee($otherUser->full_name);
});

it('lets an admin revoke another users token', function () {
    $admin = User::factory()->admin()->create();
    $otherUser = User::factory()->create();
    $tokenToRevoke = $otherUser->createToken('forgotten laptop token')->accessToken;
    $tokenToKeep = $otherUser->createToken('current laptop token')->accessToken;

    Livewire::actingAs($admin)
        ->test(ApiTokens::class)
        ->set('showAll', true)
        ->call('revoke', $tokenToRevoke->id);

    expect($otherUser->tokens()->pluck('id')->all())->toBe([$tokenToKeep->id]);
});

it('never shows other peoples tokens or the show-all switch to a regular user', function () {
    $regularUser = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherUser->createToken('someone elses token');

    Livewire::actingAs($regularUser)
        ->test(ApiTokens::class)
        ->assertDontSee('Show all tokens')
        ->set('showAll', true)
        ->assertDontSee('someone elses token');

    expect($otherUser->tokens()->count())->toBe(1);
});

it('links to the token screen from the sidebar', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/')->assertSee(route('api-tokens'));
});

it('creates a named token and surfaces the plaintext for copying', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test(ApiTokens::class)
        ->set('tokenName', 'my laptop cli')
        ->call('create')
        ->assertHasNoErrors();

    expect($user->tokens()->count())->toBe(1);
    expect($user->tokens()->sole()->name)->toBe('my laptop cli');

    $plainText = $component->get('newPlainTextToken');
    expect($plainText)->not->toBeEmpty();
    expect($user->tokens()->sole()->token)->not->toBe($plainText);
    $component->assertSee($plainText);

    // Blade directives are NOT compiled inside component tag attributes, so a
    // literal @js( reaching the browser means the copy button's Alpine
    // expression is broken (illegal character U+0040).
    $component->assertDontSee('@js(');
});

it('reads show-all from the query string, where it arrives as a string', function () {
    $admin = User::factory()->admin()->create();
    $otherUser = User::factory()->create();
    $otherUser->createToken('forgotten laptop token');

    Livewire::actingAs($admin)
        ->withQueryParams(['showAll' => '1'])
        ->test(ApiTokens::class)
        ->assertSee('forgotten laptop token');

    Livewire::actingAs($admin)
        ->withQueryParams(['showAll' => '0'])
        ->test(ApiTokens::class)
        ->assertDontSee('forgotten laptop token');
});
