<?php

use App\Mcp\Servers\BokkuServer;
use Laravel\Mcp\Facades\Mcp;

// OAuth discovery routes for MCP clients
Mcp::oauthRoutes();

// Bokku MCP server with OAuth authentication
Mcp::web('/mcp', BokkuServer::class)
    ->middleware('auth:api');
