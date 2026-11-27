<?php

declare(strict_types=1);

namespace AIC\Component\Application;

use AIC\Component\Domain\HtmlEndTag;
use AIC\Component\Domain\HtmlPolicy;
use AIC\Component\Domain\HtmlStartTag;
use AIC\Component\Domain\HtmlText;
use AIC\Component\Domain\SanitizedHtml;

use function array_reverse;
use function array_slice;
use function count;
use function in_array;
use function str_contains;

/**
 * Turns untrusted HTML into markup that is safe to send (#337), inside the
 * request and the same byte for byte under PHP and the compiled site: AIC's
 * own tokenizer, no DOM. A tag its policy allows is written again with the
 * allowed attributes, each value escaped and a link kept only for `http`,
 * `https`, `mailto` or a relative URL (with `rel="nofollow noopener"`); any
 * other tag is dropped and its text kept, a raw text element's content
 * dropped. A start tag closes what a browser would close for it
 * (`HtmlNesting`), end tags close back to their open element, a stray one
 * is dropped, and every element left open closes at the end.
 */
final readonly class HtmlSanitizer
{
    /** The allowed elements that have no end tag. */
    private const array VOID = ['br', 'hr'];

    /**
     * @param HtmlPolicy    $htmlPolicy    What it keeps.
     * @param HtmlTokenizer $htmlTokenizer Reads the input.
     * @param HtmlEscaper   $htmlEscaper   Writes text and values again.
     * @param HtmlNesting   $htmlNesting   The end tags a start tag implies.
     */
    public function __construct(
        private HtmlPolicy $htmlPolicy = new HtmlPolicy(),
        private HtmlTokenizer $htmlTokenizer = new HtmlTokenizer(),
        private HtmlEscaper $htmlEscaper = new HtmlEscaper(),
        private HtmlNesting $htmlNesting = new HtmlNesting(),
    ) {
    }

    /**
     * @param string $html Untrusted HTML, as a visitor wrote it.
     *
     * @return SanitizedHtml The markup that is safe to send.
     */
    public function sanitize(string $html): SanitizedHtml
    {
        $markup = '';
        $open = [];

        foreach ($this->htmlTokenizer->tokens($html) as $token) {
            if ($token instanceof HtmlText) {
                $markup .= $this->htmlEscaper->text($token->text);

                continue;
            }

            if ($token instanceof HtmlStartTag) {
                $sibling = $this->htmlNesting->implied($open, $token->tag);
                $markup .= $this->closes($open, $sibling) . $this->start($token);
                $open = $this->opened($this->closed($open, $sibling), $token);

                continue;
            }

            $markup .= $this->closes($open, $token);
            $open = $this->closed($open, $token);
        }

        return new SanitizedHtml($markup . $this->closes($open, new HtmlEndTag($open[0] ?? '')));
    }

    /**
     * @param HtmlStartTag $htmlStartTag A start tag.
     *
     * @return string It written again with its allowed attributes; empty when the policy drops it.
     */
    private function start(HtmlStartTag $htmlStartTag): string
    {
        if (!$this->htmlPolicy->allows($htmlStartTag->tag)) {
            return '';
        }

        $written = '<' . $htmlStartTag->tag;

        foreach ($this->htmlPolicy->attributes($htmlStartTag->tag) as $name) {
            $written .= $this->attribute($name, $htmlStartTag->attributes[$name] ?? null);
        }

        $link = $htmlStartTag->tag === 'a' && str_contains($written, ' href="');

        return $written . ($link ? ' rel="nofollow noopener"' : '') . '>';
    }

    /**
     * @param string      $name  An allowed attribute.
     * @param string|null $value Its value in the tag; null when the tag has none.
     *
     * @return string It written again; empty when the tag has none or its link is refused.
     */
    private function attribute(string $name, ?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $kept = $name === 'href'
            ? $this->htmlEscaper->href($value)
            : $value;

        if ($name === 'href' && $kept === '') {
            return '';
        }

        return ' ' . $name . '="' . $this->htmlEscaper->attribute($kept) . '"';
    }

    /**
     * @param list<string> $open         The allowed elements open now, outermost first.
     * @param HtmlStartTag $htmlStartTag A start tag.
     *
     * @return list<string> The elements open after it.
     */
    private function opened(array $open, HtmlStartTag $htmlStartTag): array
    {
        $kept = $this->htmlPolicy->allows($htmlStartTag->tag) && !in_array($htmlStartTag->tag, self::VOID, true);

        return $kept
            ? [...$open, $htmlStartTag->tag]
            : $open;
    }

    /**
     * @param list<string> $open       The allowed elements open now, outermost first.
     * @param HtmlEndTag   $htmlEndTag An end tag.
     *
     * @return string The end tags it writes: its own and every element open inside it; none when it is not open.
     */
    private function closes(array $open, HtmlEndTag $htmlEndTag): string
    {
        $written = '';

        foreach (array_reverse(array_slice($open, $this->depth($open, $htmlEndTag))) as $element) {
            $written .= '</' . $element . '>';
        }

        return $written;
    }

    /**
     * @param list<string> $open       The allowed elements open now, outermost first.
     * @param HtmlEndTag   $htmlEndTag An end tag.
     *
     * @return list<string> The elements open after it.
     */
    private function closed(array $open, HtmlEndTag $htmlEndTag): array
    {
        return array_slice($open, 0, $this->depth($open, $htmlEndTag));
    }

    /**
     * @param list<string> $open       The allowed elements open now, outermost first.
     * @param HtmlEndTag   $htmlEndTag An end tag.
     *
     * @return int Where its element is open, the innermost; the count of open elements when it is not open.
     */
    private function depth(array $open, HtmlEndTag $htmlEndTag): int
    {
        for ($offset = count($open) - 1; $offset >= 0; $offset -= 1) {
            if ($open[$offset] === $htmlEndTag->element) {
                return $offset;
            }
        }

        return count($open);
    }
}
