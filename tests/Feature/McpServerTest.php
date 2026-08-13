<?php

use App\Models\Note;
use App\Models\OAuthUser;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

beforeEach(function () {
    [$publicKey, $privateKey] = passportTestKeys();
    Config::set('passport.public_key', $publicKey);
    Config::set('passport.private_key', $privateKey);
});

/**
 * Passport's guard needs a real RSA keypair even to reject a tokenless
 * request. Generate one per test process rather than committing key
 * files to the repo.
 *
 * @return array{string, string}
 */
function passportTestKeys(): array
{
    static $keys = null;

    if ($keys === null) {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($resource, $privateKey);
        $keys = [openssl_pkey_get_details($resource)['key'], $privateKey];
    }

    return $keys;
}

it('rejects unauthenticated tool calls', function () {
    $response = $this->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => ['name' => 'add-note', 'arguments' => ['title' => 'Nope', 'body' => 'Nope.']],
    ]);

    $response->assertUnauthorized();
    expect(Note::count())->toBe(0);
});

it('serves oauth discovery metadata without authentication', function () {
    $authServer = $this->getJson('/.well-known/oauth-authorization-server');

    $authServer->assertSuccessful();
    expect($authServer->json('authorization_endpoint'))->not->toBeNull();
    expect($authServer->json('token_endpoint'))->not->toBeNull();
    expect($authServer->json('registration_endpoint'))->not->toBeNull();

    $protectedResource = $this->getJson('/.well-known/oauth-protected-resource');
    $protectedResource->assertSuccessful();
});

it('rejects unauthenticated requests to the mcp endpoint', function () {
    $response = $this->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
    ]);

    $response->assertUnauthorized();
});

it('rejects unauthenticated stream-accepting requests with a 401 rather than a login redirect', function () {
    // Real MCP clients send this Accept header. Our exception rendering
    // redirects non-JSON requests to the SSO login page, so this pins
    // that /mcp never turns a missing token into a 302.
    $response = $this->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
    ], ['Accept' => 'text/event-stream, application/json']);

    $response->assertUnauthorized();
});

it('authenticates a real bearer token through the passport guard and oauth_users provider', function () {
    // Passport::actingAs bypasses the guard, so this is the one test that
    // exercises the oauth_users provider mapping to OAuthUser for real.
    $user = User::factory()->create();
    app(ClientRepository::class)->createPersonalAccessGrantClient('Test personal access client', 'oauth_users');
    $token = OAuthUser::findOrFail($user->id)->createToken('mcp', ['mcp:use'])->accessToken;

    $response = $this->withToken($token)->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-06-18',
            'capabilities' => (object) [],
            'clientInfo' => ['name' => 'pest', 'version' => '1.0'],
        ],
    ]);

    $response->assertSuccessful();
    expect($response->json('result.serverInfo.name'))->toBe('devnotes');
});

it('completes an mcp handshake for an authenticated user', function () {
    $user = User::factory()->create();
    Passport::actingAs(OAuthUser::findOrFail($user->id));

    $response = $this->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-06-18',
            'capabilities' => (object) [],
            'clientInfo' => ['name' => 'pest', 'version' => '1.0'],
        ],
    ]);

    $response->assertSuccessful();
    expect($response->json('result.serverInfo.name'))->toBe('devnotes');
});
