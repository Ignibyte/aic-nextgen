<?php

declare(strict_types=1);

namespace AIC\Form\Application;

use AIC\Component\Domain\HiddenInput;
use AIC\Form\Exception\InvalidEnvelope;
use AIC\Secret\Application\HmacSigner;
use AIC\Session\Domain\StringMapJson;
use JsonException;
use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;
use function base64_decode;
use function base64_encode;
use function is_array;
use function is_string;
use function rtrim;
use function strpos;
use function strtr;
use function substr;

/**
 * Signed envelopes (#43): string values the server gives a form, which come
 * back unaltered or not at all. An envelope is the values as base64url JSON,
 * a dot, and their HMAC; a form carries it as the hidden `aic_envelope`.
 */
final readonly class EnvelopeCodec
{
    /**
     * The form field that carries an envelope.
     */
    public const string FIELD = 'aic_envelope';

    /**
     * @param HmacSigner    $hmacSigner    Signs with the app secret.
     * @param StringMapJson $stringMapJson Encodes the values.
     */
    public function __construct(
        private HmacSigner $hmacSigner,
        private StringMapJson $stringMapJson = new StringMapJson(),
    ) {
    }

    /**
     * @param array<string, string> $values The values to seal.
     *
     * @throws JsonException When they cannot be encoded.
     *
     * @return string The envelope.
     */
    public function seal(array $values): string
    {
        $body = rtrim(strtr(base64_encode($this->stringMapJson->encode($values)), '+/', '-_'), '=');

        return $body . '.' . $this->hmacSigner->sign($body);
    }

    /**
     * @param array<string, string> $values The values to seal.
     *
     * @throws JsonException When they cannot be encoded.
     *
     * @return HiddenInput The hidden field that carries them.
     */
    public function field(array $values): HiddenInput
    {
        return new HiddenInput(self::FIELD, $this->seal($values));
    }

    /**
     * @param ServerRequestInterface $serverRequest A posted form.
     *
     * @throws InvalidEnvelope When its envelope is missing, malformed or altered.
     *
     * @return array<string, string> The envelope's values.
     */
    public function open(ServerRequestInterface $serverRequest): array
    {
        return $this->openToken(self::envelope($serverRequest));
    }

    /**
     * @param string $envelope An envelope as seal() made it, such as a component's props token (#74).
     *
     * @throws InvalidEnvelope When it is empty, malformed or altered.
     *
     * @return array<string, string> Its values.
     */
    public function openToken(string $envelope): array
    {
        $dot = strpos($envelope, '.');
        $body = $dot === false
            ? ''
            : substr($envelope, 0, $dot);

        if ($dot === false || $body === '' || !$this->hmacSigner->verify($body, substr($envelope, $dot + 1))) {
            throw new InvalidEnvelope('The envelope is missing or was altered.');
        }

        $json = base64_decode(strtr($body, '-_', '+/'), true);

        return $this->stringMapJson->decode($json === false ? '' : $json);
    }

    /**
     * @param ServerRequestInterface $serverRequest A posted form.
     *
     * @return string Its `aic_envelope` field; empty when absent.
     */
    private static function envelope(ServerRequestInterface $serverRequest): string
    {
        $parsed = $serverRequest->getParsedBody();
        return is_array($parsed) && array_key_exists(self::FIELD, $parsed) && is_string($parsed[self::FIELD])
            ? $parsed[self::FIELD]
            : '';
    }
}
