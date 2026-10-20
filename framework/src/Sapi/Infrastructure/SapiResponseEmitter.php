<?php

declare(strict_types=1);

namespace AIC\Frankenphp\Infrastructure;

use AIC\Frankenphp\Contracts\SapiSink;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

use function array_keys;
use function sprintf;
use function strtolower;

/**
 * Sends a PSR-7 response through the SAPI sink: the status line, each header,
 * then the body.
 *
 * `Set-Cookie` goes out one line per value, since cookies cannot be folded into
 * one comma-separated line (RFC 6265 section 3); every other header goes out as
 * one line.
 */
final readonly class SapiResponseEmitter
{
    /**
     * @param SapiSink $sapiSink Where the response goes out.
     */
    public function __construct(
        private SapiSink $sapiSink,
    ) {
    }

    /**
     * @param ResponseInterface $response The response to send.
     *
     * @throws RuntimeException When the body cannot be read.
     */
    public function emit(ResponseInterface $response): void
    {
        $status = $response->getStatusCode();
        $this->sapiSink->status($status);
        $this->sapiSink->header(
            sprintf('HTTP/%s %d %s', $response->getProtocolVersion(), $status, $response->getReasonPhrase()),
            true,
        );

        foreach (array_keys($response->getHeaders()) as $name) {
            $this->emitHeader($response, (string) $name);
        }

        $stream = $response->getBody();

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        $this->sapiSink->write($stream->getContents());
    }

    /**
     * @param ResponseInterface $response The response.
     * @param string            $name     One of its header names.
     */
    private function emitHeader(ResponseInterface $response, string $name): void
    {
        if (strtolower($name) !== 'set-cookie') {
            $this->sapiSink->header($name . ': ' . $response->getHeaderLine($name), true);

            return;
        }

        foreach ($response->getHeader($name) as $value) {
            $this->sapiSink->header($name . ': ' . $value, false);
        }
    }
}
