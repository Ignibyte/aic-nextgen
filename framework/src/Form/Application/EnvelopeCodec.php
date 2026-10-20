<?php

declare(strict_types=1);

namespace AIC\Form\Application;

use AIC\Component\Contracts\Html;
use AIC\Form\Exception\InvalidEnvelope;
use AIC\Secret\Application\HmacSigner;
use AIC\Session\Domain\StringMapJson;
use JsonException;
use Psr\Http\Message\ServerRequestInterface;

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
        private StringMapJson $stringMapJson,
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
     * @return Html The hidden field that carries them.
     */
    public function field(array $values): Html
    {
        return new Html('<input type="hidden" name="' . self::FIELD . '" value="' . $this->seal($values) . '">');
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
        $envelope = self::envelope($serverRequest);
        $dot = strpos($envelope, '.');
        $body = $dot === false
            ? ''
            : substr($envelope, 0, $dot);

        if ($body === '' || !$this->hmacSigner->verify($body, substr($envelope, $dot + 1))) {
            throw new InvalidEnvelope("The form's envelope is missing or was altered.");
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
        $field = is_array($parsed)
            ? $parsed[self::FIELD] ?? ''
            : '';

        return is_string($field)
            ? $field
            : '';
    }
}
