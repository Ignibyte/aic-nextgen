<?php

declare(strict_types=1);

namespace Gates\Markup;

use Gates\Domain\RegexCaptures;

use function array_key_exists;
use function in_array;
use function preg_match;
use function str_contains;
use function str_starts_with;
use function strlen;
use function strpos;
use function strspn;
use function strtolower;
use function substr;

/**
 * What gate:32 counts as HTML in a text (#156). A hole, where a literal meets a value only known at run time, is a
 * NUL byte in the text.
 */
final readonly class MarkupText
{
    use RegexCaptures;

    /** The marker a folded literal uses for a hole. */
    public const string HOLE = "\0";

    /**
     * Every element in the WHATWG HTML index, with `svg` and `math`, and the obsolete elements browsers still parse.
     */
    private const array ELEMENTS = [
        'a', 'abbr', 'address', 'area', 'article', 'aside', 'audio', 'b', 'base', 'bdi', 'bdo', 'blockquote', 'body', 'br',
        'button', 'canvas', 'caption', 'cite', 'code', 'col', 'colgroup', 'data', 'datalist', 'dd', 'del', 'details', 'dfn',
        'dialog', 'div', 'dl', 'dt', 'em', 'embed', 'fieldset', 'figcaption', 'figure', 'footer', 'form', 'h1', 'h2', 'h3',
        'h4', 'h5', 'h6', 'head', 'header', 'hgroup', 'hr', 'html', 'i', 'iframe', 'img', 'input', 'ins', 'kbd', 'label',
        'legend', 'li', 'link', 'main', 'map', 'mark', 'menu', 'meta', 'meter', 'nav', 'noscript', 'object', 'ol',
        'optgroup', 'option', 'output', 'p', 'param', 'picture', 'pre', 'progress', 'q', 'rp', 'rt', 'ruby', 's', 'samp',
        'script', 'search', 'section', 'select', 'slot', 'small', 'source', 'span', 'strong', 'style', 'sub', 'summary',
        'sup', 'table', 'tbody', 'td', 'template', 'textarea', 'tfoot', 'th', 'thead', 'time', 'title', 'tr', 'track', 'u',
        'ul', 'var', 'video', 'wbr', 'svg', 'math', 'acronym', 'applet', 'basefont', 'bgsound', 'big', 'blink', 'center',
        'dir', 'font', 'frame', 'frameset', 'image', 'isindex', 'keygen', 'listing', 'marquee', 'menuitem', 'multicol',
        'nextid', 'nobr', 'noembed', 'noframes', 'plaintext', 'rb', 'rtc', 'spacer', 'strike', 'tt', 'xmp',
    ];

    /** The characters a tag name is read from. */
    private const string NAME = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789._-';

    /** What may follow a tag's name. */
    private const array AFTER = ['', ' ', "\t", "\n", "\r", "\f", '/', '>', self::HOLE];

    /**
     * @param string $text       A folded literal, or a text file's line.
     * @param bool   $literal    Whether it is a PHP literal, where a bracket left before a hole counts (T3).
     * @param bool   $controller Whether it sits in a controller, where any `<` counts (T5).
     *
     * @return string|null What it holds that is HTML, quoted for a message; null when it holds none.
     */
    public function find(string $text, bool $literal, bool $controller): ?string
    {
        $offset = strpos($text, '<');

        while ($offset !== false) {
            $found = $this->bracket(substr($text, $offset + 1), $literal);

            if ($found !== null) {
                return $found;
            }

            $offset = strpos($text, '<', $offset + 1);
        }

        return $this->reference($text) ?? ($controller && str_contains($text, '<') ? 'a "<" in a controller' : null);
    }

    /**
     * @param string $rest    What follows one `<`.
     * @param bool   $literal Whether a bracket left before a hole counts.
     *
     * @return string|null The tag, declaration or computed bracket it opens; null for none.
     */
    private function bracket(string $rest, bool $literal): ?string
    {
        return $this->declaration($rest)
            ? 'the markup declaration "<' . substr($rest, 0, 8) . '"'
            : $this->tag($rest, $literal);
    }

    /**
     * @param string $rest    What follows one `<` that opens no declaration.
     * @param bool   $literal Whether a bracket left before a hole counts.
     *
     * @return string|null The tag or computed bracket it opens; null for none.
     */
    private function tag(string $rest, bool $literal): ?string
    {
        $closing = str_starts_with($rest, '/')
            ? '/'
            : '';
        $rest = substr($rest, strlen($closing));
        $name = substr($rest, 0, strspn($rest, self::NAME));
        $after = substr($rest, strlen($name), 1);

        if ($this->tagName($name) && in_array($after, self::AFTER, true)) {
            return 'the HTML tag "<' . $closing . $name . '"';
        }

        return $literal && $this->computed($name, $after)
            ? 'a "<' . $closing . $name . '" left for a computed tag'
            : null;
    }

    /**
     * @param string $rest What follows one `<`.
     *
     * @return bool Whether it opens a comment, a doctype or a CDATA section.
     */
    private function declaration(string $rest): bool
    {
        return str_starts_with($rest, '!--')
            || str_starts_with(strtolower($rest), '!doctype')
            || str_starts_with($rest, '![CDATA[');
    }

    /**
     * @param string $name  The name-like run after a `<`.
     * @param string $after What follows it: a character, or empty at the end of the text.
     *
     * @return bool Whether it is a bracket a value fills at run time: a name-like run, maybe empty, then a hole or
     *              the end.
     */
    private function computed(string $name, string $after): bool
    {
        return preg_match('/^[A-Za-z0-9_-]*\\z/', $name) === 1 && ($after === self::HOLE || $after === '');
    }

    /**
     * @param string $name A tag's name.
     *
     * @return bool Whether it is an HTML element, in any case, or a custom element's name.
     */
    private function tagName(string $name): bool
    {
        return in_array(strtolower($name), self::ELEMENTS, true) || preg_match('/^[a-z][a-z0-9._]*-[a-z0-9._-]*\\z/', $name) === 1;
    }

    /**
     * @param string $text A text.
     *
     * @return string|null The first character reference in it; null for none.
     */
    private function reference(string $text): ?string
    {
        $match = self::regexMatch('/&(?:[A-Za-z0-9]{2,32}|#[0-9]+|#[xX][0-9A-Fa-f]+);/', $text);

        return array_key_exists(0, $match)
            ? 'the character reference "' . $match[0] . '"'
            : null;
    }
}
