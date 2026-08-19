<?php

use App\Models\OAuthUser;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Laravel\Passport\Passport;
use Laravel\Sanctum\Sanctum;

it('rate limits the api to 60 requests per minute per user', function () {
    Sanctum::actingAs(User::factory()->create());

    foreach (range(1, 60) as $request) {
        $this->getJson('/api/v1/teams')->assertSuccessful();
    }

    $this->getJson('/api/v1/teams')->assertStatus(429);

    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/v1/teams')->assertSuccessful();
});

it('rate limits the mcp endpoint to 60 requests per minute per user', function () {
    [$publicKey, $privateKey] = passportTestKeys();
    Config::set('passport.public_key', $publicKey);
    Config::set('passport.private_key', $privateKey);
    $initialize = [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-06-18',
            'capabilities' => (object) [],
            'clientInfo' => ['name' => 'pest', 'version' => '1.0'],
        ],
    ];
    Passport::actingAs(OAuthUser::findOrFail(User::factory()->create()->id));

    foreach (range(1, 60) as $request) {
        $this->postJson('/mcp', $initialize)->assertSuccessful();
    }

    $this->postJson('/mcp', $initialize)->assertStatus(429);

    Passport::actingAs(OAuthUser::findOrFail(User::factory()->create()->id));
    $this->postJson('/mcp', $initialize)->assertSuccessful();
});
