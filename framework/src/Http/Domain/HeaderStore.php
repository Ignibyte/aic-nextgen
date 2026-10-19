<?php

declare(strict_types=1);

namespace AIC\Http\Domain;

use InvalidArgumentException;

use function array_key_exists;
use function implode;
use function preg_match;
use function str_contains;
use function strtolower;
use function trim;

/**
 * Message's header store: validation, the flat `\n`-joined storage, and the
 * add, prepend and remove operations the withers build on. A trait keeps Message
 * under PHPMD's class-length limit; the using class holds `$headers` and
 * `$headerNames`.
 */
trait HeaderStore
{
    /**
     * A header name: an RFC 9110 token.
     */
    private const string HEADER_NAME_PATTERN = '/^[!#$%&\'*+\\-.^_`|~0-9A-Za-z]+$/';

    /**
     * The separator between one header's values in the store.
     */
    private const string VALUE_SEPARATOR = "\n";

    /**
     * Add values to a header, keeping the case of its first setting.
     *
     * @param string       $name   The header name.
     * @param list<string> $values The values to add, trimmed of surrounding whitespace.
     *
     * @throws InvalidArgumentException When the name is not a token, there is no value,
     *                                  or a value contains a line break.
     */
    final protected function addHeaderValues(string $name, array $values): void
    {
        $joined = self::joined($name, $values);
        $existing = $this->joinedValues($name);

        if ($existing !== null) {
            $this->headers[$this->headerNames[strtolower($name)]] = $existing . self::VALUE_SEPARATOR . $joined;

            return;
        }

        $this->headerNames[strtolower($name)] = $name;
        $this->headers[$name] = $joined;
    }

    /**
     * Set a header first among the headers, replacing any earlier setting of it.
     *
     * @param string $name  The header name.
     * @param string $value Its one value.
     *
     * @throws InvalidArgumentException When the name or the value is invalid.
     */
    final protected function prependHeader(string $name, string $value): void
    {
        $this->removeHeader($name);
        $joined = self::joined($name, [$value]);
        $headers = [$name => $joined];
        $names = [strtolower($name) => $name];

        foreach ($this->headers as $stored => $values) {
            $headers[$stored] = $values;
            $names[strtolower($stored)] = $stored;
        }

        $this->headers = $headers;
        $this->headerNames = $names;
    }

    /**
     * Remove a header; nothing happens when it is not set.
     *
     * @param string $name The header name, in any case.
     */
    final protected function removeHeader(string $name): void
    {
        $lower = strtolower($name);

        if (!array_key_exists($lower, $this->headerNames)) {
            return;
        }

        $headers = $this->headers;
        unset($headers[$this->headerNames[$lower]]);
        $this->headers = $headers;

        $names = $this->headerNames;
        unset($names[$lower]);
        $this->headerNames = $names;
    }

    /**
     * @param string $name The header name, in any case.
     *
     * @return string|null The header's values joined by VALUE_SEPARATOR, or null when it is not set.
     */
    private function joinedValues(string $name): ?string
    {
        $lower = strtolower($name);

        if (!array_key_exists($lower, $this->headerNames)) {
            return null;
        }

        return $this->headers[$this->headerNames[$lower]];
    }

    /**
     * @param string       $name   The header name.
     * @param list<string> $values Its values.
     *
     * @throws InvalidArgumentException When the name is not a token, there is no value,
     *                                  or a value contains a line break.
     *
     * @return string The values, trimmed, joined by VALUE_SEPARATOR.
     */
    private static function joined(string $name, array $values): string
    {
        if (preg_match(self::HEADER_NAME_PATTERN, $name) !== 1) {
            throw new InvalidArgumentException('Invalid header name: ' . $name);
        }

        if ($values === []) {
            throw new InvalidArgumentException('A header needs at least one value: ' . $name);
        }

        $trimmed = [];

        foreach ($values as $value) {
            $trimmed[] = self::trimmedValue($name, $value);
        }

        return implode(self::VALUE_SEPARATOR, $trimmed);
    }

    /**
     * @param string $name  The header name, for the message.
     * @param string $value One header value.
     *
     * @throws InvalidArgumentException When the value contains a line break.
     *
     * @return string The value without surrounding spaces and tabs.
     */
    private static function trimmedValue(string $name, string $value): string
    {
        if (str_contains($value, "\r") || str_contains($value, "\n")) {
            throw new InvalidArgumentException('A header value must not contain a line break: ' . $name);
        }

        return trim($value, " \t");
    }
}
