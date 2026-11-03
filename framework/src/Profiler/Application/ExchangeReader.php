<?php

declare(strict_types=1);

namespace AIC\Profiler\Application;

use AIC\Auth\Application\Authenticator;
use AIC\Controller\Application\ApiHandler;
use AIC\Controller\Application\PageHandler;
use AIC\Controller\Application\StreamHandler;
use AIC\Form\Contracts\FormToken;
use AIC\Profiler\Domain\RequestDetail;
use AIC\Session\Application\RequestSession;
use AIC\Session\Contracts\Session;
use AIC\Session\Domain\StoredSession;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

use function array_key_exists;
use function array_keys;
use function hash;
use function is_array;
use function preg_replace;
use function str_starts_with;
use function substr;

/**
 * Reads what a profile keeps from a request and its answer (#229, #230): the
 * route's kind, the signed-in user, a key for the browser, the header and
 * field names, and a Tessera answer's markup. Only names leave a header or a
 * field; the session id leaves only as a hash.
 */
final readonly class ExchangeReader
{
    /** The most of a Tessera answer's markup a profile keeps. */
    private const int BODY = 65_536;

    /** The CSRF token a fragment answer carries for Tessera, whose value a profile never keeps. */
    private const string CSRF_META = '/(<meta name="tessera-csrf-token" content=")[^"]*(")/';

    /**
     * A form's hidden CSRF field as the `HiddenInput` atom writes it, its value the same token (#237).
     */
    private const string CSRF_FIELD = '/(<input type="hidden" name="' . FormToken::FIELD . '" value=")[^"]*(")/';

    /** A Tessera fragment answer's media type. */
    private const string FRAGMENTS = 'text/vnd.tessera+html';

    /**
     * @param RequestHandlerInterface $requestHandler What answered the route.
     *
     * @return string The route's kind; empty for a handler that is none of the three.
     */
    public function kind(RequestHandlerInterface $requestHandler): string
    {
        return match (true) {
            $requestHandler instanceof PageHandler => 'Page',
            $requestHandler instanceof ApiHandler => 'Api',
            $requestHandler instanceof StreamHandler => 'Stream',
            default => '',
        };
    }

    /**
     * @param ServerRequestInterface $serverRequest A request past the session middleware.
     *
     * @return string The signed-in user's id; empty for a visitor or without a session.
     */
    public function user(ServerRequestInterface $serverRequest): string
    {
        $session = $this->session($serverRequest);

        return $session instanceof Session
            ? $session->get(Authenticator::USER) ?? ''
            : '';
    }

    /**
     * @param ServerRequestInterface $serverRequest A request past the session middleware.
     *
     * @return string The first 16 hex digits of the SHA-256 of its session's id; empty without one.
     */
    public function browser(ServerRequestInterface $serverRequest): string
    {
        $session = $this->session($serverRequest);

        return $session instanceof StoredSession
            ? substr(hash('sha256', $session->sessionId()), 0, 16)
            : '';
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     * @param ResponseInterface      $response      Its answer.
     * @param string                 $kind          The route's kind.
     * @param string                 $browser       The browser's key.
     *
     * @return RequestDetail The names and, for a Tessera answer, its markup.
     */
    public function detail(
        ServerRequestInterface $serverRequest,
        ResponseInterface $response,
        string $kind,
        string $browser,
    ): RequestDetail {
        $fields = [];
        $parsedBody = $serverRequest->getParsedBody();

        if (is_array($parsedBody)) {
            foreach (array_keys($parsedBody) as $name) {
                $fields[] = (string) $name;
            }
        }

        $headers = [];

        foreach (array_keys($serverRequest->getHeaders()) as $name) {
            $headers[] = (string) $name;
        }

        return new RequestDetail($kind, $browser, $headers, $fields, $this->body($response));
    }

    /**
     * @param ResponseInterface $response An answer.
     *
     * @return string Its markup when it is a Tessera fragment answer, at most 64 KiB; empty otherwise, or when the
     *                body cannot be rewound for the emitter.
     */
    private function body(ResponseInterface $response): string
    {
        $stream = $response->getBody();

        if (!str_starts_with($response->getHeaderLine('Content-Type'), self::FRAGMENTS) || !$stream->isSeekable()) {
            return '';
        }

        $markup = (string) $stream;

        try {
            $stream->rewind();
        } catch (RuntimeException) {
            return '';
        }

        return substr($this->redacted($markup), 0, self::BODY);
    }

    /**
     * @param string $markup A fragment answer's markup.
     *
     * @return string It with the CSRF token's value replaced by `redacted`, in the meta and in every form's field.
     */
    private function redacted(string $markup): string
    {
        $meta = preg_replace(self::CSRF_META, '${1}redacted${2}', $markup) ?? '';

        return preg_replace(self::CSRF_FIELD, '${1}redacted${2}', $meta) ?? '';
    }

    /**
     * @param ServerRequestInterface $serverRequest A request.
     *
     * @return Session|null Its session, when the session middleware put one on it.
     */
    private function session(ServerRequestInterface $serverRequest): ?Session
    {
        $attributes = $serverRequest->getAttributes();

        return array_key_exists(RequestSession::ATTRIBUTE, $attributes)
            && $attributes[RequestSession::ATTRIBUTE] instanceof Session
            ? $attributes[RequestSession::ATTRIBUTE]
            : null;
    }
}
