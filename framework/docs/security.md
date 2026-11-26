# Security

The framework supplies session storage, a mandatory form guard, signed envelopes, redirect
validation, upload checks, authentication and authorization. App code still decides, through its
policies, which records a user may read or change. Use the normal request stack and typed Page/Api responses rather than bypassing
middleware. [systems.md](systems.md) maps the security namespaces; [tessera.md](tessera.md)
describes enhanced requests.

## Content-Security-Policy

Every HTML response `AIC\Http\Application\ResponseBuilder` makes carries `ResponseBuilder::POLICY`
(#173): `default-src 'self'; img-src 'self' data:; object-src 'none'; base-uri 'self';
form-action 'self'; frame-ancestors 'none'`. No inline script, inline style or other origin runs.
Load scripts and styles from the theme's assets; Tessera's csp build needs nothing more.

## Sessions

`AIC\Session\Application\SessionMiddleware` loads a Postgres-backed session and attaches it to
the PSR-7 request. Read it through `AIC\Session\Application\RequestSession`. Session payloads
are string maps. An unknown or expired incoming ID receives a fresh ID, rather than adopting the
client's value. Regeneration replaces the prior stored session; an empty session is discarded.

The cookie is `__Host-aic_session`, with Secure, HttpOnly, SameSite=Lax, Path=/ and no Domain.
Serve the app over HTTPS. Its configured TTL is refreshed on saved requests. Do not put credentials,
session IDs or CSRF tokens in logs or links. A service must receive the current request/session
when needed instead of retaining it as shared mutable state.

## Form guard

`AIC\Form\Application\FormGuardMiddleware` runs after the session and before routing an unsafe
DELETE, PATCH, POST or PUT. It compares the submitted token to the session token with a constant-time
comparison. A nonempty header takes precedence over the hidden field. Missing or mismatched tokens
get 403, or 419 when a Tessera request header is present. A nonempty honeypot gets 400. Controllers
cannot opt out by returning a different Page response. A request with an `Authorization: Bearer`
header passes (#313): a browser cannot send that header to another site without a CORS preflight,
which AIC never answers, and such a request is judged by its token alone (see bearer tokens below).
So does a webhook delivery whose signature matched (see webhooks below), and a body sent as
`application/json` (#331): a browser sends that type to another site only after a CORS preflight,
which AIC never answers (forms send three other types, and a `no-cors` fetch drops the header). The
route's need still judges such a request, so an anonymous JSON `POST /mcp` meets the 401 challenge
rather than the CSRF 403. A form-encoded post without a session's token still fails, so a sign-in
form stays closed to login CSRF.

`AIC\Form\Contracts\FormToken` defines these checked names:

| Constant | Purpose |
|---|---|
| `FormToken::FIELD = aic_csrf` | hidden token field |
| `FormToken::HEADER = X-AIC-CSRF` | request token header |
| `FormToken::HONEYPOT = aic_website` | field left empty by a visitor |
| `FormToken::SESSION_KEY = aic.csrf` | token in the server session |

Build forms with `AIC\Component\Domain\Form` and the guard fields from
`FormTokens::guard()`: an `AIC\Component\Domain\GuardFields` holding the issued token, which the
form renders first as the hidden field and the honeypot (#246). A theme component that writes its
own `<form>` (for Tessera attributes) places the same atom inside it, `<?= $card->guardFields ?>`,
rather than copying the two inputs.
Prepare that string in the caller; a template reads ready properties and calls no methods.
Validate domain inputs again in the posted handler. On validation failure return the form's data as a PageDocument with
422; enhanced requests can receive its regions in place. A token proves the request came through
the session; it does not prove ownership of the record being edited.

## Signed envelopes and redirects

`AIC\Form\Application\EnvelopeCodec` seals a string map as base64url JSON plus an HMAC. The
payload is readable and signed, not encrypted. Keep private information on the server. An altered,
malformed or missing envelope raises `AIC\Form\Exception\InvalidEnvelope`.

| Operation | Contract |
|---|---|
| `EnvelopeCodec::seal()` | string map to a signed token |
| `EnvelopeCodec::field()` | string map to a HiddenInput named aic_envelope |
| `EnvelopeCodec::open()` | PSR-7 posted request to verified values |
| `EnvelopeCodec::openToken()` | a token string to verified values, including component props |

An envelope preserves values the server issued. Recheck authorization and current domain state
when those values come back. A stale signed value can still be stale business data.

Use `AIC\Form\Domain\SameOriginPath` when taking a redirect destination from input. It limits the
value to a path on this site and refuses unsafe forms. Do not turn an unvalidated next parameter
into a Location header. PageRedirect refuses any location that is not a path on this site, by the
same rule, `SameOriginPath::PATTERN` (#207), so a forgotten check fails instead of redirecting away.
PageRedirect defaults to 303 after a successful form post; enhanced replies
follow the [Tessera redirect contract](tessera.md#forms-redirects-and-write-identifiers).

## Uploads

Multipart input becomes PSR-7 UploadedFileInterface values.
`AIC\Form\Application\UploadCheck` checks upload errors, a nonzero size, the configured maximum
and the first bytes of the stream. Client filenames and client media types do not decide the kind,
and gate:13 refuses either one reaching a write: a storage key, a database value, the session, a
queued job, a notification, mail or a download's headers (#199). Name a stored file yourself.
The accepted enum cases are:

| Kind | Content |
|---|---|
| `UploadKind::Png = Png` | PNG image |
| `UploadKind::Jpeg = Jpeg` | JPEG image |
| `UploadKind::Gif = Gif` | GIF image |
| `UploadKind::WebP = WebP` | WebP image |
| `UploadKind::Pdf = Pdf` | PDF document |
| `UploadKind::Text = Text` | text |

Configure the permitted kinds and byte limit for the feature, handle
`AIC\Form\Exception\RefusedUpload`, then choose a server-owned destination. A matching signature
is a format check, not a malware scan or a safe-execution guarantee. Never execute an upload or use
its supplied name as an unchecked filesystem path.

### Stored files

An accepted upload goes to the app's stores (#213), never to a path built from what the client sent:
`UploadCheck::kind()` answers its kind and the extension it is stored under, and a module names the
key. `PublicFiles` holds what any visitor may fetch. The framework serves it at `/files/<key>`
before any session: `public, no-cache` with a strong ETag, `X-Content-Type-Options: nosniff` and
`Content-Security-Policy: sandbox`, and inline only for images (PNG, JPEG, GIF, WebP, AVIF), PDF and
plain text; any other file, an SVG included, is an attachment, so stored content never runs as the
site. `PrivateFiles` has no URL: a module's own Stream checks who asks before it answers
`download()`. A key is `/`-joined segments of letters, digits, `.`, `_` and `-`, none starting with
a dot, so it never climbs out of its store or names a hidden file. Private files are written
`0600`, public ones `0644`.

### The request body cap

`AIC\Config\Domain\AppConfig::$maxBodyBytes` (default 8 MiB, the compiled server's own) is the
largest request body the app takes. `AIC\Form\Application\BodyLimitMiddleware` answers a larger one
413 before the session is read, by its `Content-Length`, so PHP and the compiled site refuse it
alike. Set it above the largest upload a feature allows, so a file over the feature's own limit
reaches `UploadCheck` and is refused in place with its reason. The servers in front must let the cap
through, and the compiled site's heap must hold it ([native.md](native.md#whole-site-comparison-and-diagnosis)).
gate:23 and `aic-gate serve` build the compiled site with a `--heap-size` sized from the cap and start
it with `--max-body-size`, and start php -S with `post_max_size` 1 MiB above the cap and
`upload_max_filesize` at it. A deployment does the same for the compiled binary and for php-fpm or
nginx (`client_max_body_size`).

## Authentication and app secret

`AIC\Auth\Application\Authenticator` uses the app's UserProvider, regenerates the session ID on
sign-in and ends authentication on logout. A login with no user is checked against a decoy hash, so
it costs the bcrypt comparison a wrong password costs and its timing does not reveal which logins
exist (#177). `login()` answers an `AIC\Auth\Domain\SignedInUser` (the user's id and the
`profile` fields the provider put on its `UserCredentials`, such as a name and a role, never the
hash) or null, so a sign-in reads the user once (#238). Password hashing uses Postgres pgcrypto bcrypt through
`AIC\Auth\Application\PgcryptoPasswordHasher`. Declare protected route prefixes in the module
manifest; anonymous visitors are redirected to sign-in with a validated same-origin next path.
Authentication is separate from authorization, below.

## Authorization

`AIC\Access` (#304) adds what a signed-in user may do. There is still no user entity: a user is
the id the app's provider signed in.

- **Permissions are code.** A module declares each, `->permission('notes.delete-pinned', 'Delete a
  pinned note.')`: lower-case words in two or more parts joined by `.`, once across the app.
- **Roles are data.** `aic_role_permissions` holds what each role grants and `aic_user_roles` what
  each user holds. The operator changes them: `bin/aic role:grant|role:revoke <role> <permission>`
  and `bin/aic role:assign|role:unassign <user> <role>`. A grant is not checked against the
  declared names; `build/surface.json` lists each module's permissions.
- **A route's need** (`->route(..., needs: 'notes.edit')`, or `needs: 'signed-in'`) is checked
  before its controller: the generated wiring hands it to the kind's adapter, which wraps the
  controller in `AIC\Access\Application\PermissionGate`. A user without it gets 403, plain text.
  The build refuses an undeclared need, a handler whose routes need different things, and a route
  under a protected prefix that says nothing (so a new page there cannot be left open by mistake).
- **A record's decision** comes from the module's `AIC\Access\Contracts\Policy` for it, in
  `src/Policy/`: each public method is an action, `delete(Actor $actor, Note $note): Decision`, or
  `create(Actor $actor): Decision` before there is a record, and gate:8 holds every policy to that
  shape. A controller takes the policy and
  `AIC\Access\Application\Actors`, and asks before it writes:
  `$this->notePolicy->delete($this->actors->actor($request), $note)->allows()`. `Actor::can()`
  answers for one permission; nothing finds a policy at run time. `bin/aic make:policy <module>
  <Entity>` writes one with a `create` action and declares the permission it asks; the agent adds
  the record's actions. A policy is API for its controllers, so it carries `@api`.

## Bearer tokens

A server-to-server caller reaches a route with a bearer token, as RFC 6750 says (#313). Scopes are
permissions: a token holds names the modules declare with `->permission()`, and a route's `needs:`
is met by a user's role or a token's scope alike.

- **The operator issues them.** `bin/aic token:add <name> <token> <scope>...` takes the value as
  `user:add` takes a password: 32 visible characters or more, chosen by the operator (`openssl rand
  -hex 32`). `aic_api_tokens` keeps the name, the scopes and the SHA-256 of the value, never the
  value. `bin/aic token:list` shows names and scopes; `bin/aic token:revoke <name>` ends one on the
  next request. A token lives until it is revoked; a short-lived one comes from the authorization
  server (see OAuth below).
- **One credential per request.** `AIC\Http\Domain\BearerCredential` finds the token. With one,
  `AIC\Access\Application\Actors` answers the token's actor (`Actor::$client` is its name, its
  scopes are the permissions) and never reads the session's user. `signed-in` is a person's need,
  which no token meets. Such a request skips the CSRF check and the sign-in redirect of a protected
  prefix; its route's need judges it.
- **Refusals follow RFC 6750.** `PermissionGate` answers an unknown or revoked token 401 with
  `WWW-Authenticate: Bearer error="invalid_token"`, and a known token without the need 403 with
  `error="insufficient_scope", scope="<need>"`. A request with no token keeps the plain 403, unless
  the authorization server is on (see OAuth below).

## OAuth

AIC is its own OAuth 2 authorization server when `AppConfig::$oauth` names an issuer (#331):
`new OAuthServer('https://example.com')`, https or http on a loopback host, an origin with no path.
With none it is off and none of this answers. An operator-registered client gets a short-lived
bearer token by the client credentials grant, or, acting for a signed-in person (an MCP client),
by the authorization code grant with PKCE.

- **The operator registers clients.** `bin/aic client:add <id> <secret>|- <scope>...
  [--redirect=<uri>]...` adds one: a secret (32 visible characters or more, kept as its SHA-256) makes
  it confidential, `-` public, which may only use the code grant. Its scopes are permissions, the most
  it may ever hold; each redirect URI is compared exactly, https or a loopback http. `client:list`
  shows them; `client:revoke <id>` removes the client with its codes and tokens.
- **The token endpoint answers before the session.** `AIC\OAuth\Application\OAuthMount` sits beside
  the profiler's mount and takes `POST /oauth/token` (form body, no CSRF: the client authenticates
  itself) and the two metadata documents; everything else passes on. The client authenticates by
  HTTP Basic or by `client_id` and `client_secret` in the body, not both. `client_credentials`
  (confidential clients) grants the scopes asked for, all the client's when none are, refusing any
  it does not hold. `authorization_code` takes the code once, checks the redirect URI and the S256
  `code_verifier`, and grants what the code holds. The answer is RFC 6749's JSON (`access_token`,
  `token_type`, `expires_in`, `scope`) with `Cache-Control: no-store`; a refusal is RFC 6749 §5.2's
  error, `invalid_client` 401 with `WWW-Authenticate: Basic realm="oauth"`.
- **Tokens and codes are opaque and hashed.** `RandomTokens::fresh()` values, kept as SHA-256 in
  `aic_oauth_tokens` (`tokenSeconds`, 3600 by default) and `aic_oauth_codes` (`codeSeconds`, 60). No
  JWT, no refresh token. `BearerTokens` reads both token tables, so an issued token is a bearer
  token: its actor holds the granted scopes and, for a code-flow token, the person's user id. It never
  meets `signed-in`, as no token does. An expired one is `invalid_token`.
- **Discovery.** `GET /.well-known/oauth-authorization-server` is RFC 8414's document and
  `/.well-known/oauth-protected-resource` RFC 9728's (a path after it names the resource). With
  OAuth on, `PermissionGate` answers a visitor (no session user, no token) refused a permission need
  401 with `WWW-Authenticate: Bearer resource_metadata="…"`, and adds the parameter to
  `invalid_token`; a signed-in person without the permission keeps the 403. This is how an MCP
  client finds where to sign in.
- **The consent page is a module's.** It needs a signed-in person, CSRF on the decision and markup,
  so a module routes `GET` and `POST /oauth/authorize` (under a protected prefix, `needs:
  'signed-in'`) to its own Page, which hands the request to `AIC\OAuth\Application\Authorizations`.
  `read()` checks the client and its exact redirect URI and throws `OAuthRefusedException` when
  either is wrong, so a bad one is shown to the person and never redirected to. A request with any
  other fault (`response_type`, a missing S256 challenge, a `resource` naming another site) carries
  its refusal, which `refuse()` sends back to the client. `grantable()` is what the client asked for,
  holds and the person's roles grant; `allow()` issues the code and answers a
  `AIC\Controller\Domain\ClientRedirect` (a 303 only to the registered URI) with `code` and `state`.
  The sign-in redirect of a protected prefix keeps the query, so the request survives signing in.

## Webhooks

Another system posts to a webhook with a body signed by a secret both sides hold (#321).
`AppConfig::$webhooks` names each one by its path: `new Webhooks(['/hooks/alerts' => new
Webhook('X-Signature', $setting('AIC_WEBHOOK_ALERTS_SECRET', ''))])`, the secret read from the
environment in `config/app.php`.

- **The framework checks the signature.** `AIC\Http\Application\WebhookSignatureMiddleware` runs
  after the route errors and before the session. A POST to a configured path answers 401 unless
  its header is the hex HMAC-SHA256 of the raw body under the secret, compared in constant time,
  with or without a `sha256=` prefix (`WebhookScheme::HmacSha256`, the only scheme). A blank
  secret refuses every delivery.
- **A verified delivery skips the CSRF check.** It goes on carrying the request attribute
  `Webhook::ATTRIBUTE`, which only server code can set, and the form guard passes it. A path the
  config does not name keeps the CSRF check, so a webhook route with no config entry answers 403.
- **The endpoint answers at once.** The Api `bin/aic make:webhook` writes queues a job with the
  body and answers 202, so the sender never waits on the work and does not retry a slow one.

## Sign-in through a provider

`AIC\Auth\Application\OidcSignIn` signs a person in through an OpenID Connect provider such as
Keycloak, by the authorization code flow with PKCE (#313). `AppConfig::$oidc` names the providers
(`AIC\Auth\Domain\OidcProviders` of `OidcProvider`: issuer, authorization and token endpoints, the
key set's `jwksUri`, client id and secret, callback, scopes, and the `algorithms` its id tokens are
signed with, `RS256` (the default), `ES256` or both). Each URL is https, or http on a loopback host for
a local stub; an empty secret is a public client. A callback written as a path joins the request's
origin.

- `start($request, $name)` keeps a fresh state, nonce and S256 verifier in the session and answers
  `AIC\Controller\Domain\ProviderRedirect`, the one page response that leaves the site, and only for a
  configured authorization endpoint. Start it from a POST, so the form guard keeps another site from
  starting a sign-in in a visitor's session.
- `finish($request)` clears what start kept, checks the state, exchanges the code through the app's
  PSR-18 client (`AIC\Auth\Application\CodeExchange`, HTTP Basic for a confidential client), checks
  the id token's signature and then its claims with `AIC\Auth\Domain\IdTokenClaims`: issuer, audience
  (and `azp`), expiry and nonce. It answers an `OidcIdentity` (provider, subject, email) or throws
  `AIC\Auth\Contracts\SignInRefusedException` with a reason for the person.
- The signature (#343): `CodeExchange::keys()` fetches the provider's JSON Web Key Set once per
  sign-in, so a rotated key is seen at once. `AIC\Auth\Application\IdTokenSignature` refuses `alg:
  none` and any algorithm the provider does not declare, takes the key the header's `kid` names (or the
  one key of the algorithm's type), and verifies the signature with `openssl_verify` and SHA-256: RSA
  PKCS#1 v1.5 for RS256, ECDSA on P-256 for ES256. `AIC\Auth\Domain\PublicKeyDer` builds the key's
  PEM from its JWK components and an ES256 signature's DER from its `r` and `s`, since the compiled
  site has no `openssl_pkey_new` from components. The compiled site links elephc's `openssl-crypto`
  package for it, pinned in ignibyte/aic-gates' `native/elephc.toml`.
- Who the identity is in the app is the caller's decision; `Authenticator::signInAs($request,
  $userId)` regenerates the session and signs that id in. The fixture signs in `<provider>:<subject>`.

The typed secret config obtains the app secret from `AIC_APP_SECRET`; environment access belongs
in `config/`. Supply a strong private secret per deployment, outside versioned files. Changing it
invalidates existing signatures. Never embed it in templates, public assets or client props.

Gate:8 checks types and unsafe API use, gate:13 performs ordinary and taint analysis, gate:15 checks
security/source rules, and gate:17 checks secrets. Gate:23 compares configured session, form and
upload behavior between native and PHP. A passing gate proves its covered cases; inspect the
actual request, cookie/session continuity and response when a form unexpectedly expires.
