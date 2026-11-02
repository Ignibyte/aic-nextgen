<?php

declare(strict_types=1);

namespace AIC\Controller\Domain;

use InvalidArgumentException;

use function mb_check_encoding;
use function preg_match;

/**
 * One attribute of an `XmlElement` (#233): a name and its value, checked
 * when made. `XmlDocument` escapes the value when it writes it.
 */
final readonly class XmlAttribute
{
    /**
     * An XML name: letters, digits, `.`, `_` and `-`, not led by a digit, `.` or `-`, with one optional `prefix:`.
     */
    private const string NAME = '/^(?:[A-Za-z_][A-Za-z0-9._-]*:)?[A-Za-z_][A-Za-z0-9._-]*$/D';

    /**
     * A character XML 1.0 forbids, in text already checked to be UTF-8 (natively a `/u` match on other bytes
     * does not fail as PHP's does, so the encoding is checked first).
     */
    private const string FORBIDDEN = '/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F\\x{FFFE}\\x{FFFF}]/u';

    /**
     * @param string $name  Its name.
     * @param string $value Its value, as text.
     *
     * @throws InvalidArgumentException When the name is not an XML name or the value holds a character XML 1.0 forbids.
     */
    public function __construct(
        public string $name,
        public string $value,
    ) {
        if (preg_match(self::NAME, $name) !== 1) {
            throw new InvalidArgumentException('An XML attribute needs an XML name; got "' . $name . '".');
        }

        if (!mb_check_encoding($value, 'UTF-8') || preg_match(self::FORBIDDEN, $value) !== 0) {
            throw new InvalidArgumentException('The XML attribute ' . $name . ' holds a character XML 1.0 forbids.');
        }
    }
}
