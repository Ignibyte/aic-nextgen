<?php

declare(strict_types=1);

namespace AIC\Tessera\Application;

use AIC\Form\Application\EnvelopeCodec;
use AIC\Form\Exception\InvalidEnvelope;
use AIC\Tessera\Domain\TesseraRequest;
use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;

/**
 * A component's props as Tessera sends them back (#74, protocol 2.5): the
 * token its opening marker carried, from `Tessera-Targets`, verified before
 * anything reads it. A token is signed, not encrypted.
 */
final readonly class TesseraProps
{
    /**
     * @param EnvelopeCodec $envelopeCodec Opens and verifies the tokens it sealed.
     */
    public function __construct(
        private EnvelopeCodec $envelopeCodec,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     * @param string                 $target        The component's path, such as `content/stock-list`.
     *
     * @throws InvalidEnvelope When the request carries a token for it that is malformed or altered.
     *
     * @return array<string, string> Its props; empty when the request sent none for it.
     */
    public function forTarget(ServerRequestInterface $serverRequest, string $target): array
    {
        $token = self::tesseraRequest($serverRequest)->props($target);

        return $token === ''
            ? []
            : $this->envelopeCodec->openToken($token);
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     * @param string                 $target        The component's path.
     * @param string                 $version       What the component's data is now, as its props seal it.
     *
     * @throws InvalidEnvelope When the request carries a token for it that is malformed or altered.
     *
     * @return bool Whether the request is a refresh of that component alone whose props carry that version, so a
     *              `PageUnchanged` answers it (#193).
     */
    public function unchanged(ServerRequestInterface $serverRequest, string $target, string $version): bool
    {
        if (!self::tesseraRequest($serverRequest)->refreshesOnly($target)) {
            return false;
        }

        $props = $this->forTarget($serverRequest, $target);

        return array_key_exists('version', $props) && $props['version'] === $version;
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @return TesseraRequest Its Tessera headers.
     */
    private static function tesseraRequest(ServerRequestInterface $serverRequest): TesseraRequest
    {
        return new TesseraRequest(
            $serverRequest->getHeaderLine(TesseraRequest::REQUEST),
            $serverRequest->getHeaderLine(TesseraRequest::PROTOCOL),
            $serverRequest->getHeaderLine(TesseraRequest::TARGETS),
            $serverRequest->getHeaderLine(TesseraRequest::LAYOUT),
        );
    }
}
