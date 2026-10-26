<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * One start tag as it was written (#170), with the text of a raw-text
 * element (`script`, `style`, `textarea`, `title`) up to its end tag.
 */
final readonly class HtmlTag
{
    /**
     * @param string              $name       The element name, lower case.
     * @param list<HtmlAttribute> $attributes Its attributes, in order.
     * @param int                 $line       The line its `<` is on.
     * @param string              $tail       What is written between the last attribute and `>`: whitespace and a `/`.
     * @param string              $body       A raw-text element's text; empty for any other element.
     */
    public function __construct(
        public string $name,
        public array $attributes,
        public int $line,
        public string $tail,
        public string $body,
    ) {
    }

    /**
     * @param string $name An attribute name, lower case.
     *
     * @return HtmlAttribute|null The first attribute of that name; null when the tag has none.
     */
    public function attribute(string $name): ?HtmlAttribute
    {
        foreach ($this->attributes as $attribute) {
            if ($attribute->name === $name) {
                return $attribute;
            }
        }

        return null;
    }
}
