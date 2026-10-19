<?php

declare(strict_types=1);

namespace AIC\Http\Domain;

use InvalidArgumentException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * The PSR-7 response.
 *
 * A status without a reason phrase takes the phrase IANA registers for it; an
 * unregistered status has an empty one.
 */
final class Response extends Message implements ResponseInterface
{
    /**
     * The lowest status RFC 9110 allows.
     */
    private const int MIN_STATUS = 100;

    /**
     * The highest status RFC 9110 allows.
     */
    private const int MAX_STATUS = 599;

    /**
     * The IANA status registry's reason phrases (RFC 9110 section 15).
     */
    private const array DEFAULT_REASON_PHRASES = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        103 => 'Early Hints',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        208 => 'Already Reported',
        226 => 'IM Used',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Content Too Large',
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => "I'm a teapot",
        421 => 'Misdirected Request',
        422 => 'Unprocessable Content',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Too Early',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
    ];

    /**
     * The reason phrase for the status.
     */
    private string $reasonPhrase;

    /**
     * @param int             $statusCode      The status, 100 to 599.
     * @param StreamInterface $stream          The response body.
     * @param string          $protocolVersion The HTTP version.
     * @param string          $reasonPhrase    The reason phrase; empty takes the registered one.
     *
     * @throws InvalidArgumentException When the status is out of range.
     */
    public function __construct(
        private int $statusCode,
        StreamInterface $stream,
        string $protocolVersion = '1.1',
        string $reasonPhrase = '',
    ) {
        self::assertStatus($statusCode);

        parent::__construct($protocolVersion, $stream);

        $this->reasonPhrase = self::reasonPhraseFor($statusCode, $reasonPhrase);
    }

    /**
     * @return int The status.
     */
    #[Override]
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @param int    $code         The status for the copy, 100 to 599.
     * @param string $reasonPhrase The reason phrase; empty takes the registered one.
     *
     * @throws InvalidArgumentException When the status is out of range.
     *
     * @return static A copy with that status.
     */
    #[Override]
    public function withStatus(int $code, string $reasonPhrase = ''): static
    {
        self::assertStatus($code);
        $new = clone $this;
        $new->statusCode = $code;
        $new->reasonPhrase = self::reasonPhraseFor($code, $reasonPhrase);

        return $new;
    }

    /**
     * @return string The reason phrase; may be empty.
     */
    #[Override]
    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    /**
     * @param int $code The status to check.
     *
     * @throws InvalidArgumentException When it is outside 100 to 599.
     */
    private static function assertStatus(int $code): void
    {
        if ($code < self::MIN_STATUS || $code > self::MAX_STATUS) {
            throw new InvalidArgumentException('An HTTP status must be 100 to 599; got ' . $code . '.');
        }
    }

    /**
     * @param int    $code     The status.
     * @param string $explicit The caller's phrase; it wins when not empty.
     *
     * @return string The reason phrase.
     */
    private static function reasonPhraseFor(int $code, string $explicit): string
    {
        return $explicit !== '' ? $explicit : self::DEFAULT_REASON_PHRASES[$code] ?? '';
    }
}
