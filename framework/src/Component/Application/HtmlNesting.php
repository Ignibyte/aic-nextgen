<?php

declare(strict_types=1);

namespace AIC\Component\Application;

use AIC\Component\Domain\HtmlEndTag;

use function count;
use function in_array;

/**
 * The end tags a start tag implies, as a browser's parser applies them (#337),
 * so the sanitizer writes them itself and its markup parses without an error:
 * a block closes an open paragraph, a heading closes a heading it is in, a
 * list item closes the one open in its list, a link closes an open link.
 */
final readonly class HtmlNesting
{
    /** The elements whose start tag closes an open `p`. */
    private const array BLOCKS = ['blockquote', 'h2', 'h3', 'h4', 'hr', 'ol', 'p', 'pre', 'ul'];

    /** The headings. */
    private const array HEADINGS = ['h2', 'h3', 'h4'];

    /**
     * @param list<string> $open The allowed elements open now, outermost first.
     * @param string       $tag  An allowed start tag.
     *
     * @return HtmlEndTag The end tag it implies: back to the element it closes; one that closes nothing otherwise.
     */
    public function implied(array $open, string $tag): HtmlEndTag
    {
        $depth = match (true) {
            $tag === 'li' => $this->depth($open, 'li', ['ol', 'ul']),
            $tag === 'a' => $this->depth($open, 'a', []),
            in_array($tag, self::BLOCKS, true) => $this->depth($open, 'p', []),
            default => count($open),
        };
        $depth = in_array($tag, self::HEADINGS, true)
            ? $this->heading($open, $depth)
            : $depth;

        return new HtmlEndTag($open[$depth] ?? '');
    }

    /**
     * @param list<string> $open  The allowed elements open now, outermost first.
     * @param int          $depth Where the element a heading's start tag closes first is; the count when none is.
     *
     * @return int Where the heading open at the top is once that is closed, which it closes too; the depth otherwise.
     */
    private function heading(array $open, int $depth): int
    {
        $top = $depth - 1;

        return $top >= 0 && in_array($open[$top] ?? '', self::HEADINGS, true)
            ? $top
            : $depth;
    }

    /**
     * @param list<string> $open     The allowed elements open now, outermost first.
     * @param string       $element  The element looked for.
     * @param list<string> $boundary The elements it is not looked for past.
     *
     * @return int Where the innermost of it is open inside the boundary; the count of open elements otherwise.
     */
    private function depth(array $open, string $element, array $boundary): int
    {
        for ($depth = count($open) - 1; $depth >= 0; $depth -= 1) {
            if ($open[$depth] === $element) {
                return $depth;
            }

            if (in_array($open[$depth], $boundary, true)) {
                break;
            }
        }

        return count($open);
    }
}
