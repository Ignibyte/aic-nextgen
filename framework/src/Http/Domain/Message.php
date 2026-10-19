<?php

declare(strict_types=1);

namespace AIC\Http\Domain;

use InvalidArgumentException;
use Override;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;

use function array_key_exists;
use function explode;
use function implode;
use function is_array;
use function is_float;
use function is_int;
use function is_string;
use function strtolower;

/**
 * The PSR-7 message base shared by ServerRequest and Response: protocol version,
 * headers and body.
 *
 * Headers keep the case they were set with and are looked up case-insensitively.
 * Each header's values are stored joined by "\n", which no header value may
 * contain: the store stays a flat `array<string, string>`, and a value with a
 * line break is refused, which also closes header injection.
 *
 * A message starts with no headers; they are set through the withers. Abstract
 * by design: PSR-7 puts these eleven methods on every message, and the withers
 * clone and assign, so the properties are protected and not readonly.
 */
abstract class Message implements MessageInterface
{
    use HeaderStore;

    /**
     * Header values by the name they were set with, joined by VALUE_SEPARATOR.
     *
     * @var array<string, string>
     */
    protected array $headers = [];

    /**
     * Stored header name by lower-cased name.
     *
     * @var array<string, string>
     */
    protected array $headerNames = [];

    /**
     * @param string          $protocolVersion The HTTP version, such as `1.1`.
     * @param StreamInterface $body            The message body.
     */
    protected function __construct(
        protected string $protocolVersion,
        protected StreamInterface $body,
    ) {
    }

    /**
     * @return string The HTTP version.
     */
    #[Override]
    final public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    /**
     * @param string $version The HTTP version for the copy.
     *
     * @return static A copy with that version.
     */
    #[Override]
    final public function withProtocolVersion(string $version): static
    {
        $new = clone $this;
        $new->protocolVersion = $version;

        return $new;
    }

    /**
     * @return array<string, list<string>> Every header's values, by the name it was set with.
     */
    #[Override]
    final public function getHeaders(): array
    {
        $headers = [];

        foreach ($this->headers as $name => $joined) {
            $headers[$name] = explode(self::VALUE_SEPARATOR, $joined);
        }

        return $headers;
    }

    /**
     * @param string $name The header name, in any case.
     *
     * @return bool Whether the header is set.
     */
    #[Override]
    final public function hasHeader(string $name): bool
    {
        return array_key_exists(strtolower($name), $this->headerNames);
    }

    /**
     * @param string $name The header name, in any case.
     *
     * @return list<string> The header's values; an empty list when it is not set.
     */
    #[Override]
    final public function getHeader(string $name): array
    {
        $joined = $this->joinedValues($name);

        return $joined === null
            ? []
            : explode(self::VALUE_SEPARATOR, $joined);
    }

    /**
     * @param string $name The header name, in any case.
     *
     * @return string The header's values joined by a comma; empty when it is not set.
     */
    #[Override]
    final public function getHeaderLine(string $name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    /**
     * @param string $name  The header name.
     * @param mixed  $value A string or a list of strings; a number is taken as its string.
     *
     * @throws InvalidArgumentException When the name or a value is invalid.
     *
     * @return static A copy with the header set to these values.
     */
    #[Override]
    final public function withHeader(string $name, mixed $value): static
    {
        return $this->withoutHeader($name)->withAddedHeader($name, $value);
    }

    /**
     * @param string $name  The header name.
     * @param mixed  $value A string or a list of strings; a number is taken as its string.
     *
     * @throws InvalidArgumentException When the name or a value is invalid.
     *
     * @return static A copy with these values added to the header.
     */
    #[Override]
    final public function withAddedHeader(string $name, mixed $value): static
    {
        $values = [];

        foreach (is_array($value) ? $value : [$value] as $entry) {
            $values[] = match (true) {
                is_string($entry) => $entry,
                is_int($entry), is_float($entry) => (string) $entry,
                default => throw new InvalidArgumentException('A header value must be a string or a number.'),
            };
        }

        $new = clone $this;
        $new->addHeaderValues($name, $values);

        return $new;
    }

    /**
     * @param string $name The header name, in any case.
     *
     * @return static A copy without the header.
     */
    #[Override]
    final public function withoutHeader(string $name): static
    {
        $new = clone $this;
        $new->removeHeader($name);

        return $new;
    }

    /**
     * @return StreamInterface The message body.
     */
    #[Override]
    final public function getBody(): StreamInterface
    {
        return $this->body;
    }

    /**
     * @param StreamInterface $body The body for the copy.
     *
     * @return static A copy with that body.
     */
    #[Override]
    final public function withBody(StreamInterface $body): static
    {
        $new = clone $this;
        $new->body = $body;

        return $new;
    }
}
