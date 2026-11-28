<?php

declare(strict_types=1);

/*
 * The chat module's manifest, made by bin/aic make:module (#122). The build
 * reads it; nothing reads it at run time. It holds the socket fixture (#32):
 * rooms over `/ws/chat`, served by bin/aic socket:serve.
 */

use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Chat\Controller\ChatSocket;

return (new ModuleManifest('chat'))
    ->requires('site')
    ->route(RouteMethod::Get, '/ws/chat', ChatSocket::class);
