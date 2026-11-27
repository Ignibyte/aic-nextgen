<?php

declare(strict_types=1);

namespace AIC\Component\Application;

use AIC\Component\Domain\HtmlEndTag;
use AIC\Component\Domain\HtmlStartTag;
use AIC\Component\Domain\HtmlStep;
use AIC\Component\Domain\HtmlText;

use function ctype_alpha;
use function in_array;
use function stripos;
use function strlen;
use function strpos;
use function strtolower;
use function substr;

/**
 * Reads HTML into text, start tags and end tags in one pass (#337), with no
 * DOM and no pattern over the whole input, so it runs the same under PHP and
 * the compiled site. Comments, doctypes and processing instructions are read
 * and left out; a tag the input leaves open ends the input; the content of a
 * raw text element (`script`, `style` and the like) is skipped to its end tag.
 */
final readonly class HtmlTokenizer
{
    /** The elements whose content is never markup, so never kept. */
    private const array RAW_TEXT = ['iframe', 'noembed', 'noframes', 'noscript', 'script', 'style', 'textarea', 'title', 'xmp'];

    /**
     * @param HtmlAttributeReader $htmlAttributeReader Reads a start tag's attributes.
     */
    public function __construct(
        private HtmlAttributeReader $htmlAttributeReader = new HtmlAttributeReader(),
    ) {
    }

    /**
     * @param string $html Untrusted HTML.
     *
     * @return list<HtmlText|HtmlStartTag|HtmlEndTag> What it holds, in order.
     */
    public function tokens(string $html): array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($html);

        while ($offset < $length) {
            $htmlStep = $this->next($html, $offset);
            $offset = $htmlStep->next;

            if ($htmlStep->token === null) {
                continue;
            }

            $tokens[] = $htmlStep->token;
        }

        return $tokens;
    }

    /**
     * @param string $html   The input.
     * @param int    $offset Where to read.
     *
     * @return HtmlStep The text up to the next `<`, or the markup there.
     */
    private function next(string $html, int $offset): HtmlStep
    {
        $open = strpos($html, '<', $offset);

        if ($open === false) {
            return new HtmlStep(new HtmlText(substr($html, $offset)), strlen($html));
        }

        return $open > $offset
            ? new HtmlStep(new HtmlText(substr($html, $offset, $open - $offset)), $open)
            : $this->markup($html, $offset);
    }

    /**
     * @param string $html   The input.
     * @param int    $offset A `<`.
     *
     * @return HtmlStep A comment or a declaration left out, an end tag, a start tag, or a `<` that opens nothing.
     */
    private function markup(string $html, int $offset): HtmlStep
    {
        if (substr($html, $offset, 4) === '<!--') {
            return new HtmlStep(null, $this->after($html, '-->', $offset + 4));
        }

        $next = substr($html, $offset + 1, 1);

        return match (true) {
            $next === '!', $next === '?' => new HtmlStep(null, $this->after($html, '>', $offset)),
            $next === '/' => $this->endTag($html, $offset),
            ctype_alpha($next) => $this->startTag($html, $offset),
            default => new HtmlStep(new HtmlText('<'), $offset + 1),
        };
    }

    /**
     * @param string $html   The input.
     * @param int    $offset A `</`.
     *
     * @return HtmlStep The end tag; `</` as text when no name follows.
     */
    private function endTag(string $html, int $offset): HtmlStep
    {
        $name = $this->htmlAttributeReader->name($html, $offset + 2);

        return $name === ''
            ? new HtmlStep(new HtmlText('</'), $offset + 2)
            : new HtmlStep(new HtmlEndTag(strtolower($name)), $this->after($html, '>', $offset));
    }

    /**
     * @param string $html   The input.
     * @param int    $offset A `<` and a letter.
     *
     * @return HtmlStep The start tag, past a raw text element's content; nothing when the tag is never closed.
     */
    private function startTag(string $html, int $offset): HtmlStep
    {
        $name = $this->htmlAttributeReader->name($html, $offset + 1);
        $tag = strtolower($name);
        $htmlAttributes = $this->htmlAttributeReader->attributes($html, $offset + 1 + strlen($name));

        if ($htmlAttributes->next < 0) {
            return new HtmlStep(null, strlen($html));
        }

        $next = $htmlAttributes->next;

        if (in_array($tag, self::RAW_TEXT, true)) {
            $end = stripos($html, '</' . $tag, $next);
            $next = $end === false
                ? strlen($html)
                : $end;
        }

        return new HtmlStep(new HtmlStartTag($tag, $htmlAttributes->values), $next);
    }

    /**
     * @param string $html   The input.
     * @param string $needle What ends the markup.
     * @param int    $offset Where to look from.
     *
     * @return int Just past the needle; the input's end when it is not there.
     */
    private function after(string $html, string $needle, int $offset): int
    {
        $found = strpos($html, $needle, $offset);

        return $found === false
            ? strlen($html)
            : $found + strlen($needle);
    }
}
