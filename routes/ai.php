<?php

use App\Mcp\Servers\DevnotesServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\Http\Controllers\OAuthRegisterController;

Mcp::oauthRoutes();

// oauthRoutes() registers /oauth/register with no middleware and takes no
// middleware argument, so re-register it with a per-IP throttle - for an
// identical method and URI the last registration wins. The endpoint is
// unauthenticated by design and writes a client row per call.
Route::post('oauth/register', OAuthRegisterController::class)->middleware('throttle:oauth-register');

Mcp::web('/mcp', DevnotesServer::class)
    ->middleware(['auth:api', 'throttle:api']);
