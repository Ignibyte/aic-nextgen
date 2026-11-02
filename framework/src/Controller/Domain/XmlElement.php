<?php

declare(strict_types=1);

namespace AIC\Controller\Domain;

use InvalidArgumentException;

use function mb_check_encoding;
use function preg_match;

/**
 * One element of an `XmlDocument` (#233): a name, its attributes in order,
 * its text and its child elements, checked when made. Its text is written
 * before its children; there is no other mixed content. `XmlDocument`
 * escapes the text when it writes it.
 */
final readonly class XmlElement
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
     * @param string             $name       Its name.
     * @param list<XmlAttribute> $attributes Its attributes, in order.
     * @param string             $text       Its text, as text: never markup.
     * @param list<XmlElement>   $children   Its child elements, in order, after the text.
     *
     * @throws InvalidArgumentException When the name is not an XML name or the text holds a character XML 1.0 forbids.
     */
    public function __construct(
        public string $name,
        public array $attributes = [],
        public string $text = '',
        public array $children = [],
    ) {
        if (preg_match(self::NAME, $name) !== 1) {
            throw new InvalidArgumentException('An XML element needs an XML name; got "' . $name . '".');
        }

        if (!mb_check_encoding($text, 'UTF-8') || preg_match(self::FORBIDDEN, $text) !== 0) {
            throw new InvalidArgumentException('The XML element ' . $name . ' holds a character XML 1.0 forbids.');
        }
    }
}
