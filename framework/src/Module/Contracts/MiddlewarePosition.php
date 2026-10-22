<?php

declare(strict_types=1);

namespace AIC\Module\Contracts;

/**
 * Where a module's middleware runs in the framework's stack (#93). The stack is
 * fixed: Tessera's protocol and redirects, route errors, the session, then the
 * modules' middleware, the form guard, Tessera's writes and the router.
 *
 * One position for now, the one a module needs; another joins when a module
 * needs it.
 *
 * Manifests construct it; nothing in the framework does, so it is API for them.
 *
 * @api
 */
enum MiddlewarePosition
{
    /** After the session is open, before the form guard: it sees the visitor and may answer for them. */
    case AfterSession;
}
