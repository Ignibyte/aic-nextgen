<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\HtmlTag;

use function in_array;
use function str_ends_with;
use function str_starts_with;
use function stripos;
use function strlen;
use function strpos;
use function substr;
use function substr_count;

/**
 * Lists the start tags of a page or a template's markup (#170) as the HTML
 * tokenizer finds them: comments, the doctype, CDATA, end tags and bogus
 * comments are passed over, and a raw-text element's text is kept whole, so
 * a `<` inside a script or a title is never read as a tag. PHP 8.5's
 * `Dom\HTMLDocument` is not used here: it hides a `<template>`'s content and
 * drops a table row parsed outside a table, and a component template is
 * often one.
 */
final readonly class HtmlScanner
{
    /** The elements whose text runs to their end tag. */
    private const array RAW_TEXT = ['script', 'style', 'textarea', 'title'];

    /** What the tokenizer passes over, each opener with its closer; the longer openers first. */
    private const array SKIPPED = ['<!--' => '-->', '<![CDATA[' => ']]>', '</' => '>', '<!' => '>', '<?' => '>'];

    /**
     * @param HtmlTagReader $htmlTagReader Reads each start tag.
     */
    public function __construct(
        private HtmlTagReader $htmlTagReader = new HtmlTagReader(),
    ) {
    }

    /**
     * @param string $html A page, or a template's markup.
     *
     * @return list<HtmlTag> Its start tags, in order.
     */
    public function tags(string $html): array
    {
        $tags = [];
        $offset = 0;
        $line = 1;
        $counted = 0;

        while (($open = strpos($html, '<', $offset)) !== false) {
            $line += substr_count($html, "\n", $counted, $open - $counted);
            $counted = $open;
            [$tag, $offset] = $this->startTag($html, $open, $line);

            if (!$tag instanceof HtmlTag) {
                continue;
            }

            $tags[] = $tag;
        }

        return $tags;
    }

    /**
     * @param string $html The markup.
     * @param int    $open The offset of a `<`.
     * @param int    $line Its line.
     *
     * @return array{HtmlTag|null, int} The start tag there, if one is, and where the scan goes on.
     */
    private function startTag(string $html, int $open, int $line): array
    {
        $skipped = $this->skipped($html, $open);

        if ($skipped !== null) {
            return [null, $skipped];
        }

        $read = $this->htmlTagReader->read($html, $open, $line);

        return $read === null
            ? [null, $open + 1]
            : $this->withBody($html, $read[0], $read[1]);
    }

    /**
     * @param string $html The markup.
     * @param int    $open The offset of a `<`.
     *
     * @return int|null The offset after the comment, doctype, CDATA, end tag or bogus comment there; null for none.
     */
    private function skipped(string $html, int $open): ?int
    {
        foreach (self::SKIPPED as $opener => $closer) {
            if (str_starts_with(substr($html, $open, strlen($opener)), $opener)) {
                return self::after($html, $closer, $open + strlen($opener));
            }
        }

        return null;
    }

    /**
     * @param string  $html    The markup.
     * @param HtmlTag $htmlTag A start tag.
     * @param int     $offset  The offset after its `>`.
     *
     * @return array{HtmlTag, int} The tag with a raw-text element's text, and the offset of what follows.
     */
    private function withBody(string $html, HtmlTag $htmlTag, int $offset): array
    {
        if (!in_array($htmlTag->name, self::RAW_TEXT, true) || str_ends_with($htmlTag->tail, '/')) {
            return [$htmlTag, $offset];
        }

        $end = stripos($html, '</' . $htmlTag->name, $offset);
        $end = $end === false
            ? strlen($html)
            : $end;
        $body = substr($html, $offset, $end - $offset);

        return [new HtmlTag($htmlTag->name, $htmlTag->attributes, $htmlTag->line, $htmlTag->tail, $body), $end];
    }

    /**
     * @param string $html   The markup.
     * @param string $closer What ends the passed-over part.
     * @param int    $from   Where to look for it.
     *
     * @return int The offset after the closer; the end of the markup when there is none.
     */
    private static function after(string $html, string $closer, int $from): int
    {
        $close = strpos($html, $closer, $from);

        return $close === false
            ? strlen($html)
            : $close + strlen($closer);
    }
}
