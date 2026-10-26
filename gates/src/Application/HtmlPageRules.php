<?php

declare(strict_types=1);

namespace Gates\Application;

use Dom\HTMLDocument;
use Gates\Domain\HtmlAttribute;
use Gates\Domain\HtmlFinding;
use Gates\Domain\HtmlRule;
use Gates\Domain\HtmlTag;

use function array_key_exists;
use function libxml_clear_errors;
use function libxml_get_errors;
use function libxml_use_internal_errors;
use function ltrim;
use function mb_trim;
use function preg_replace;
use function sprintf;
use function strtolower;
use function substr;

/**
 * The rules only a served page can be held to (#170): each `id` once, an
 * `alt` on every `<img>`, and no error from PHP 8.5's HTML5 parser on a full
 * document. A fragment answer has no doctype, so the parser is not asked.
 */
final readonly class HtmlPageRules
{
    /**
     * @param string        $html A served body.
     * @param list<HtmlTag> $tags Its start tags.
     *
     * @return list<HtmlFinding> Each repeated id, image without alt and parse error.
     */
    public function findings(string $html, array $tags): array
    {
        $findings = [];
        $ids = [];

        foreach ($tags as $tag) {
            [$repeated, $ids] = $this->repeatedId($tag, $ids);
            $findings = [...$findings, ...$repeated, ...$this->alt($tag)];
        }

        return [...$findings, ...$this->parseErrors($html)];
    }

    /**
     * @param HtmlTag            $htmlTag A start tag.
     * @param array<string, int> $ids     Each id seen so far, `#`-prefixed, with its line.
     *
     * @return array{list<HtmlFinding>, array<string, int>} The tag's id when it is already taken, and the ids seen.
     */
    private function repeatedId(HtmlTag $htmlTag, array $ids): array
    {
        $attribute = $htmlTag->attribute('id');
        $id = $attribute instanceof HtmlAttribute
            ? $attribute->value ?? ''
            : '';
        $key = '#' . $id;

        if ($id === '') {
            return [[], $ids];
        }

        if (array_key_exists($key, $ids)) {
            $detail = sprintf('id "%s" is already on line %d', $id, $ids[$key]);

            return [[new HtmlFinding($htmlTag->line, HtmlRule::DuplicateId, $detail)], $ids];
        }

        $ids[$key] = $htmlTag->line;

        return [[], $ids];
    }

    /**
     * @param HtmlTag $htmlTag A start tag.
     *
     * @return list<HtmlFinding> An `<img>` with no `alt`.
     */
    private function alt(HtmlTag $htmlTag): array
    {
        $detail = 'an <img> with no alt: describe it, or write alt="" for decoration';

        return $htmlTag->name === 'img' && !$htmlTag->attribute('alt') instanceof HtmlAttribute
            ? [new HtmlFinding($htmlTag->line, HtmlRule::MissingAlt, $detail)]
            : [];
    }

    /**
     * @param string $html A served body.
     *
     * @return list<HtmlFinding> The parser's tokenizer and tree errors; none for a body without a doctype.
     */
    private function parseErrors(string $html): array
    {
        if (strtolower(substr(ltrim($html), 0, 9)) !== '<!doctype') {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        HTMLDocument::createFromString($html);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $findings = [];

        foreach ($errors as $error) {
            $message = preg_replace('/ in Entity, line: \\d+, column: ([\\d-]+)\\z/', ' at column $1', mb_trim($error->message)) ?? '';
            $detail = $message . '; fix the markup the template writes';
            $findings[] = new HtmlFinding($error->line, HtmlRule::ParseError, $detail);
        }

        return $findings;
    }
}
