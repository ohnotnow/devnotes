<?php

use App\Mcp\Servers\DevnotesServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::oauthRoutes();

Mcp::web('/mcp', DevnotesServer::class)
    ->middleware(['auth:api', 'throttle:api']);
