# Security

The framework supplies session storage, a mandatory form guard, signed envelopes, redirect
validation, upload checks and authentication. App code still decides which records a user may
read or change. Use the normal request stack and typed Page/Api responses rather than bypassing
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
cannot opt out by returning a different Page response.

`AIC\Form\Contracts\FormToken` defines these checked names:

| Constant | Purpose |
|---|---|
| `FormToken::FIELD = aic_csrf` | hidden token field |
| `FormToken::HEADER = X-AIC-CSRF` | request token header |
| `FormToken::HONEYPOT = aic_website` | field left empty by a visitor |
| `FormToken::SESSION_KEY = aic.csrf` | token in the server session |

Build forms with `AIC\Component\Domain\Form` and the issued token value from `FormToken::value()`.
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
into a Location header. PageRedirect defaults to 303 after a successful form post; enhanced replies
follow the [Tessera redirect contract](tessera.md#forms-redirects-and-write-identifiers).

## Uploads

Multipart input becomes PSR-7 UploadedFileInterface values.
`AIC\Form\Application\UploadCheck` checks upload errors, a nonzero size, the configured maximum
and the first bytes of the stream. Client filenames and client media types do not decide the kind.
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

### The request body cap

`AIC\Config\Domain\AppConfig::$maxBodyBytes` (default 8 MiB, the compiled server's own) is the
largest request body the app takes. `AIC\Form\Application\BodyLimitMiddleware` answers a larger one
413 before the session is read, by its `Content-Length`, so PHP and the compiled site refuse it
alike. Set it above the largest upload a feature allows, so a file over the feature's own limit
reaches `UploadCheck` and is refused in place with its reason. The servers in front must let the cap
through: gate:23 and `aic-gate serve` start the compiled server with `--max-body-size` and php -S with
`post_max_size` 1 MiB above the cap and `upload_max_filesize` at it. A deployment does the same for
the compiled binary and for php-fpm or nginx (`client_max_body_size`).

## Authentication and app secret

`AIC\Auth\Application\Authenticator` uses the app's UserProvider, regenerates the session ID on
sign-in and ends authentication on logout. A login with no user is checked against a decoy hash, so
it costs the bcrypt comparison a wrong password costs and its timing does not reveal which logins
exist (#177). Password hashing uses Postgres pgcrypto bcrypt through
`AIC\Auth\Application\PgcryptoPasswordHasher`. Declare protected route prefixes in the module
manifest; anonymous visitors are redirected to sign-in with a validated same-origin next path.
Authentication is separate from per-record permissions, which the app must enforce.

The typed secret config obtains the app secret from `AIC_APP_SECRET`; environment access belongs
in `config/`. Supply a strong private secret per deployment, outside versioned files. Changing it
invalidates existing signatures. Never embed it in templates, public assets or client props.

Gate:8 checks types and unsafe API use, gate:13 performs ordinary and taint analysis, gate:15 checks
security/source rules, and gate:17 checks secrets. Gate:23 compares configured session, form and
upload behavior between native and PHP. A passing gate proves its covered cases; inspect the
actual request, cookie/session continuity and response when a form unexpectedly expires.
