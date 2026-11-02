<?php

declare(strict_types=1);

namespace AIC\Controller\Domain;

use function str_replace;

/**
 * An XML document (#233): one root element, written as UTF-8 text with an
 * XML declaration. Text and attribute values are escaped here and nowhere
 * else, so no caller writes markup.
 */
final readonly class XmlDocument
{
    /**
     * The declaration every document starts with.
     */
    private const string DECLARATION = '<?xml version="1.0" encoding="UTF-8"?>';

    /**
     * @param XmlElement $xmlElement The root element.
     */
    public function __construct(
        private XmlElement $xmlElement,
    ) {
    }

    /**
     * @return string The declaration, then the root element, each ended by a line feed.
     */
    public function serialized(): string
    {
        return self::DECLARATION . "\n" . $this->element($this->xmlElement) . "\n";
    }

    /**
     * @param XmlElement $xmlElement An element.
     *
     * @return string It written: its attributes, then its text and children, or `/>` when it has neither.
     */
    private function element(XmlElement $xmlElement): string
    {
        $open = '<' . $xmlElement->name . $this->attributes($xmlElement->attributes);

        if ($xmlElement->text === '' && $xmlElement->children === []) {
            return $open . '/>';
        }

        $children = '';

        foreach ($xmlElement->children as $child) {
            $children .= $this->element($child);
        }

        return $open . '>' . self::escaped($xmlElement->text) . $children . '</' . $xmlElement->name . '>';
    }

    /**
     * @param list<XmlAttribute> $attributes Attributes.
     *
     * @return string Each as ` name="value"`, in order.
     */
    private function attributes(array $attributes): string
    {
        $written = '';

        foreach ($attributes as $attribute) {
            $written .= ' ' . $attribute->name . '="' . self::escaped($attribute->value) . '"';
        }

        return $written;
    }

    /**
     * @param string $text Text.
     *
     * @return string It with `&`, `<`, `>`, `"` and `'` as entities.
     */
    private static function escaped(string $text): string
    {
        $escaped = str_replace('&', '&amp;', $text);
        $escaped = str_replace('<', '&lt;', $escaped);
        $escaped = str_replace('>', '&gt;', $escaped);
        $escaped = str_replace('"', '&quot;', $escaped);

        return str_replace("'", '&apos;', $escaped);
    }
}
