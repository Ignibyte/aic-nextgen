<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\HtmlFinding;
use Gates\Domain\HttpExchange;
use Gates\Domain\NativeSiteAnswer;
use Gates\Domain\NativeSiteStep;

use function mb_strtolower;
use function sprintf;
use function str_starts_with;
use function usort;

/**
 * gate:23's HTML lint (#170): every HTML body the PHP site answers a step
 * with is held to the inline rules and the page rules. Only the PHP side is
 * read; the compiled site must answer with the same body.
 */
final readonly class NativeSiteHtml
{
    /**
     * @param HtmlScanner     $htmlScanner     Lists each body's start tags.
     * @param HtmlInlineRules $htmlInlineRules No inline style or script.
     * @param HtmlPageRules   $htmlPageRules   Ids, image alts and parse errors.
     */
    public function __construct(
        private HtmlScanner $htmlScanner = new HtmlScanner(),
        private HtmlInlineRules $htmlInlineRules = new HtmlInlineRules(),
        private HtmlPageRules $htmlPageRules = new HtmlPageRules(),
    ) {
    }

    /**
     * @param list<NativeSiteStep>   $steps   The steps.
     * @param list<NativeSiteAnswer> $answers The PHP site's answers, one per step, as sent.
     *
     * @return list<string> One line per finding: the step and the path it was sent to, the line in its body,
     *                      the rule and the fix.
     */
    public function findings(array $steps, array $answers): array
    {
        $lines = [];

        foreach ($steps as $index => $step) {
            $answer = $answers[$index] ?? null;

            if (!$answer instanceof NativeSiteAnswer) {
                continue;
            }

            foreach ($this->body(self::html($answer->httpExchange)) as $finding) {
                $place = sprintf('%s (%s %s)', $step->name, $step->method, $answer->path);
                $lines[] = sprintf('%s: line %d: %s', $place, $finding->line, $finding->message());
            }
        }

        return $lines;
    }

    /**
     * @param string $html One served body.
     *
     * @return list<HtmlFinding> Its findings, by line.
     */
    private function body(string $html): array
    {
        $tags = $this->htmlScanner->tags($html);
        $findings = [...$this->htmlInlineRules->findings($tags), ...$this->htmlPageRules->findings($html, $tags)];
        usort($findings, static fn (HtmlFinding $one, HtmlFinding $other): int => $one->line <=> $other->line);

        return $findings;
    }

    /**
     * @param HttpExchange $httpExchange A step's answer.
     *
     * @return string Its body when it is HTML; empty otherwise.
     */
    private static function html(HttpExchange $httpExchange): string
    {
        return str_starts_with(mb_strtolower($httpExchange->contentType), 'text/html')
            ? $httpExchange->body
            : '';
    }
}
