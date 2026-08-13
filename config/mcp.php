<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Redirect Domains
    |--------------------------------------------------------------------------
    |
    | These domains are the domains that OAuth clients are permitted to use
    | for redirect URIs. Each domain should be specified with its scheme
    | and host. Domains not in this list will raise validation errors.
    |
    | An "*" may be used to allow all domains.
    |
    */

    // DELIBERATE DEV-ONLY WILDCARD (decided 2026-08-13): '*' stays while the
    // app is loopback-only on lando and colleagues are testing connectors.
    // It MUST become an explicit allowlist before any internet-reachable
    // deploy - with '*', any domain can register itself as an OAuth redirect
    // target via the unauthenticated /oauth/register endpoint. The lockdown
    // recipe and pinning tests are tracked as ait issue devnotes-gbHJd.5.4.
    // Keep 'http://localhost' allowed when tightening: CLI clients redirect
    // to ephemeral loopback callback ports.
    'redirect_domains' => [
        '*',
        // 'https://example.com',
        // 'http://localhost',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Custom Schemes
    |--------------------------------------------------------------------------
    |
    | Native desktop OAuth clients like Cursor and VS Code use private-use URI
    | schemes (RFC 8252) for redirect callbacks instead of standard schemes
    | like HTTPS. Here, you may list which custom schemes you will allow.
    |
    */

    'custom_schemes' => [
        // 'claude',
        // 'cursor',
        // 'vscode',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization Server
    |--------------------------------------------------------------------------
    |
    | Here you may configure the OAuth authorization server issuer identifier
    | per RFC 8414. This value appears in your protected resource and auth
    | server metadata endpoints. When null, this defaults to `url('/')`.
    |
    */

    'authorization_server' => null,

];
