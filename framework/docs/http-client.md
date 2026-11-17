# Calling upstream APIs

`AIC\HttpClient` (#305) is how a module calls another service over HTTP: a PSR-18 client, a pool
that sends many requests at once, an explicit retry, and the upstreams an app names in config. It
runs over ext-curl, which the compiled site links natively (curl 8.21 with OpenSSL, pinned in
`ignibyte/aic-gates`' `native/elephc.toml`).

## The roots

A module's constructor asks for what it needs by type, on the web side and the console side alike:

| Type | What it is |
|---|---|
| `Psr\Http\Client\ClientInterface` | sends one request (`CurlClient`, observed by the profiler when it records) |
| `AIC\HttpClient\Contracts\RequestPool` | sends many at once (`CurlPool`) |
| `Psr\Http\Message\RequestFactoryInterface` | makes requests (`AIC\Http\Application\RequestFactory`) |
| `Psr\Http\Message\StreamFactoryInterface` | makes their bodies |
| `AIC\HttpClient\Domain\Upstreams` | the upstreams `config/app.php` names |

The generated wiring reaches them through one bundle, `AIC\HttpClient\Application\Outbound`: the
console context's `outbound()`, the web context's `roots()->outbound()`. Nothing else builds a client: a module never writes
`new CurlClient()`.

## The client

`sendRequest()` answers every status as a response; a 404 or a 503 is not an exception. It throws
only when the request cannot be sent (`AIC\HttpClient\Contracts\RequestException`: the URI is not
an absolute `http` or `https` one), when it gets no answer (`NetworkException`: the host did not
resolve, the connection failed, the time ran out; the message is curl's text for its code), or when
the answer is not HTTP (`AnswerException`). All three are PSR-18's. Redirects are not followed. A
request may take 30 seconds in all and 5 to connect.

## The pool

`RequestPool::send()` takes `PooledRequest`s under names and answers a `PooledAnswer` under each
name, in the order given. Each request keeps its own time limit (`new PooledRequest($request,
100)` gives it 100 ms; 0 is the client's 30 seconds), and one that fails leaves the others alone:
`status()` is 0 and `failure` says why, and `response()` throws its `NetworkException`. The pool
waits for the slowest, so a page that needs three upstreams waits once. A request that cannot be
sent at all throws before any is sent.

## Retries

No client retries by itself. `AIC\HttpClient\Application\RetryingClient` wraps one with a
`RetryPolicy`: how many attempts in all (3), the wait before the second (100 ms, doubling each
time), and the statuses worth another attempt (429, 502, 503, 504). A network failure is retried
too; the last attempt's response or exception is the answer. Build it in a module factory, so the
constructor that wants retries asks for `RetryingClient`:

```php
final readonly class BillingClients
{
    public function __construct(private ClientInterface $client) {}

    public function retrying(): RetryingClient
    {
        return new RetryingClient($this->client, new RetryPolicy(3, 50));
    }
}
```

## Upstreams in config

`config/app.php` names each upstream with its base URL and token, read from the environment there
and nowhere else:

```php
upstreams: new Upstreams([
    'billing' => new Upstream($setting('AIC_UPSTREAM_BILLING', 'https://billing.example.com/v2'), $setting('AIC_UPSTREAM_BILLING_TOKEN', '')),
]),
```

`Upstreams::upstream('billing')` gives it, and throws when config names none; `Upstream::url('/invoices')`
joins a path to its base, and `Upstream::request($requestFactory, 'GET', '/invoices')` makes the
request with the token as a bearer header when there is one. Build requests through these rather
than a PSR-7 wither on an interface: the compiled site types a wither's copy as the message it is.

## A typed client

`bin/aic make:client <module> <Name>` writes `src/Service/<Name>Client.php` and the reply value its
calls answer with, `src/Service/<Name>Reply.php`, and prints the line to add to `config/app.php`.
The client knows its upstream's name (`UPSTREAM`, the name in kebab case), sends the token as a
bearer header when there is one, and answers typed values, never a PSR-7 response. Add a method per
call the upstream offers.

## What the profiler sees

With `AIC_PROFILER=1` each call, single or pooled, is a row on the request's Outbound tab: the
method, the URL, the status (0 for none) and the time. A retried call is a row per attempt. See
[profiler.md](profiler.md).
