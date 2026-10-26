<?php

declare(strict_types=1);

namespace Gates\Templates;

use Gates\Application\HtmlInlineRules;
use Gates\Application\HtmlScanner;
use Gates\Domain\HtmlAttribute;
use Gates\Domain\HtmlFinding;
use Gates\Domain\HtmlRule;
use Gates\Domain\HtmlTag;
use Gates\Domain\TemplateRefusal;
use Gates\Domain\TemplateViolation;
use PhpParser\Lexer;

use function array_map;
use function in_array;
use function preg_match;
use function sprintf;
use function str_contains;
use function str_repeat;
use function str_replace;
use function substr_count;

use const T_INLINE_HTML;
use const T_OPEN_TAG_WITH_ECHO;

/**
 * gate:25's markup rules (#170). A template's markup is read as the browser
 * will see it, every `<?= … ?>` stood in by one NUL marker and every other
 * PHP block by its newlines only, so both branches of an `if` show and each
 * line keeps its number. On top of the inline rules every page keeps, a
 * template writes each attribute name itself, quotes each value with `"`,
 * and spaces a tag one way.
 */
final readonly class TemplateHtml
{
    /** What an echo stands as in the markup. */
    private const string MARKER = "\0";

    /** One space between attributes, or a new line and its indentation (PHP blocks may add lines). */
    private const string GAP = '/\\A(?: |\\n+ *)\\z/';

    /**
     * @param HtmlScanner     $htmlScanner     Lists the markup's start tags.
     * @param HtmlInlineRules $htmlInlineRules No inline style or script.
     * @param Lexer           $lexer           Splits the source into inline HTML and PHP.
     */
    public function __construct(
        private HtmlScanner $htmlScanner = new HtmlScanner(),
        private HtmlInlineRules $htmlInlineRules = new HtmlInlineRules(),
        private Lexer $lexer = new Lexer(),
    ) {
    }

    /**
     * @param string $path   The template, relative to the root.
     * @param string $source Its source.
     *
     * @return list<TemplateViolation> One `html` violation per finding, in tag order.
     */
    public function violations(string $path, string $source): array
    {
        $tags = $this->htmlScanner->tags($this->markup($source));
        $findings = $this->htmlInlineRules->findings($tags);

        foreach ($tags as $tag) {
            $findings = [...$findings, ...$this->written($tag)];
        }

        return array_map(
            static fn (HtmlFinding $htmlFinding): TemplateViolation => new TemplateViolation(
                $path,
                $htmlFinding->line,
                TemplateRefusal::Html,
                $htmlFinding->message(),
            ),
            $findings,
        );
    }

    /**
     * @param string $source A template.
     *
     * @return string Its markup: inline HTML as written, a marker per echo, the newlines of the rest.
     */
    private function markup(string $source): string
    {
        $markup = '';

        foreach ($this->lexer->tokenize($source) as $token) {
            $markup .= match ($token->id) {
                T_INLINE_HTML => $token->text,
                T_OPEN_TAG_WITH_ECHO => self::MARKER,
                default => str_repeat("\n", substr_count($token->text, "\n")),
            };
        }

        return $markup;
    }

    /**
     * @param HtmlTag $htmlTag A start tag of the markup.
     *
     * @return list<HtmlFinding> What its writing breaks: an echoed name, a value not in double quotes,
     *                           the spacing between attributes, around `=` and before `>`.
     */
    private function written(HtmlTag $htmlTag): array
    {
        $findings = str_contains($htmlTag->name, self::MARKER)
            ? [new HtmlFinding($htmlTag->line, HtmlRule::AttributeName, 'an echo writes into a tag name: write the name itself')]
            : [];

        foreach ($htmlTag->attributes as $attribute) {
            $findings = [...$findings, ...$this->attribute($attribute)];
        }

        if (!in_array($htmlTag->tail, ['', '/'], true)) {
            $detail = sprintf('a space before the end of <%s>: write > or /> right after the last attribute', $htmlTag->name);
            $findings[] = new HtmlFinding($htmlTag->line, HtmlRule::TagSpacing, $detail);
        }

        return $findings;
    }

    /**
     * @param HtmlAttribute $htmlAttribute An attribute as written.
     *
     * @return list<HtmlFinding> Its echoed name, its quoting and its spacing.
     */
    private function attribute(HtmlAttribute $htmlAttribute): array
    {
        $findings = [];

        foreach ($this->checks($htmlAttribute) as $detail => [$htmlRule, $broken]) {
            if (!$broken) {
                continue;
            }

            $findings[] = new HtmlFinding($htmlAttribute->line, $htmlRule, $detail);
        }

        return $findings;
    }

    /**
     * @param HtmlAttribute $htmlAttribute An attribute as written.
     *
     * @return array<string, array{HtmlRule, bool}> Each rule's detail, with the rule and whether the attribute breaks it.
     */
    private function checks(HtmlAttribute $htmlAttribute): array
    {
        $name = str_replace(self::MARKER, '<?= … ?>', $htmlAttribute->name);
        return [
            'an echo writes an attribute name: write the name, and echo only its value' => [
                HtmlRule::AttributeName,
                str_contains($htmlAttribute->name, self::MARKER),
            ],
            sprintf('%s\'s value is not in double quotes: write %s="…"', $name, $name) => [
                HtmlRule::AttributeQuote,
                $htmlAttribute->value !== null && $htmlAttribute->quote !== '"',
            ],
            sprintf('a space around %s\'s =: write %s="…"', $name, $name) => [HtmlRule::TagSpacing, $htmlAttribute->spacedEquals],
            sprintf('separate %s from what comes before it with one space, or a new line and its indentation', $name) => [
                HtmlRule::TagSpacing,
                preg_match(self::GAP, $htmlAttribute->gap) !== 1,
            ],
        ];
    }
}
