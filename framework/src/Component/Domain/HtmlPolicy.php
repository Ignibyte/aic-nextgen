<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use function array_key_exists;

/**
 * What `HtmlSanitizer` keeps (#337): each allowed element with the attributes
 * it may carry, in the order they are written. Everything else is dropped.
 * The default is prose: paragraphs, emphasis, lists, quotes, code, three
 * heading levels and links, with no `style`, no `on*` and no `src`.
 */
final readonly class HtmlPolicy
{
    /** Prose. */
    public const array PROSE = [
        'a' => ['href', 'title'],
        'b' => [],
        'blockquote' => [],
        'br' => [],
        'code' => [],
        'em' => [],
        'h2' => [],
        'h3' => [],
        'h4' => [],
        'hr' => [],
        'i' => [],
        'li' => [],
        'ol' => [],
        'p' => [],
        'pre' => [],
        's' => [],
        'strong' => [],
        'u' => [],
        'ul' => [],
    ];

    /**
     * @param array<string, list<string>> $elements Each allowed element, lower case, with its allowed attributes.
     */
    public function __construct(
        private array $elements = self::PROSE,
    ) {
    }

    /**
     * @param string $element An element name, lower case.
     *
     * @return bool Whether it is kept.
     */
    public function allows(string $element): bool
    {
        return array_key_exists($element, $this->elements);
    }

    /**
     * @param string $element An allowed element, lower case.
     *
     * @return list<string> The attributes it may carry, in the order they are written.
     */
    public function attributes(string $element): array
    {
        return $this->elements[$element] ?? [];
    }
}
