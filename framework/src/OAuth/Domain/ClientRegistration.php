<?php

declare(strict_types=1);

namespace AIC\OAuth\Domain;

/**
 * Who may register a client at `POST /oauth/register` (RFC 7591, #352):
 * nobody (`Off`, the default: only the operator's `client:add`), a caller
 * whose bearer token holds `oauth.register` (`Scoped`, RFC 7591's initial
 * access token), or anyone (`Open`, as an MCP client expects of a server it
 * has never met). A registered client may hold only the server's
 * `registrationScopes`.
 */
enum ClientRegistration
{
    case Off;
    case Scoped;
    case Open;
}
