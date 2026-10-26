<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\HtmlAttribute;
use Gates\Domain\HtmlFinding;
use Gates\Domain\HtmlRule;
use Gates\Domain\HtmlTag;

use function mb_trim;
use function preg_replace;
use function sprintf;
use function str_starts_with;
use function strtolower;

/**
 * The rules every page keeps, in a template and as served (#170): no inline
 * style and no inline script. Style lives in the theme's stylesheets and
 * script in script files; a script file may still set styles (Chad,
 * 2026-10-26: "No inline styles (outside of javascript)").
 */
final readonly class HtmlInlineRules
{
    /** The fix for inline style. */
    private const string STYLE_FIX = "style the element through a class in the theme's stylesheet";

    /** The fix for inline script. */
    private const string SCRIPT_FIX = "load a script file from the theme's assets/ and bind events there";

    /**
     * @param list<HtmlTag> $tags The start tags.
     *
     * @return list<HtmlFinding> One per inline style or script, in tag order.
     */
    public function findings(array $tags): array
    {
        $findings = [];

        foreach ($tags as $tag) {
            $findings = [...$findings, ...$this->element($tag), ...$this->attributes($tag)];
        }

        return $findings;
    }

    /**
     * @param HtmlTag $htmlTag A start tag.
     *
     * @return list<HtmlFinding> A `<style>` element, or a `<script>` with no `src` or with a body.
     */
    private function element(HtmlTag $htmlTag): array
    {
        if ($htmlTag->name === 'style') {
            return [new HtmlFinding($htmlTag->line, HtmlRule::InlineStyle, 'a <style> element: ' . self::STYLE_FIX)];
        }

        $inline = $htmlTag->name === 'script'
            && (!$htmlTag->attribute('src') instanceof HtmlAttribute || mb_trim($htmlTag->body) !== '');

        $detail = 'a <script> with no src or with a body: ' . self::SCRIPT_FIX;

        return $inline
            ? [new HtmlFinding($htmlTag->line, HtmlRule::InlineScript, $detail)]
            : [];
    }

    /**
     * @param HtmlTag $htmlTag A start tag.
     *
     * @return list<HtmlFinding> Each `style` attribute, `on*` handler and `javascript:` URL.
     */
    private function attributes(HtmlTag $htmlTag): array
    {
        $findings = [];

        foreach ($htmlTag->attributes as $attribute) {
            $finding = $this->attribute($attribute);

            if (!$finding instanceof HtmlFinding) {
                continue;
            }

            $findings[] = $finding;
        }

        return $findings;
    }

    /**
     * @param HtmlAttribute $htmlAttribute An attribute.
     *
     * @return HtmlFinding|null Its inline style or script; null when it holds none.
     */
    private function attribute(HtmlAttribute $htmlAttribute): ?HtmlFinding
    {
        $line = $htmlAttribute->line;
        $name = $htmlAttribute->name;

        return match (true) {
            $name === 'style' => new HtmlFinding($line, HtmlRule::InlineStyle, 'a style attribute: ' . self::STYLE_FIX),
            str_starts_with($name, 'on') => new HtmlFinding(
                $line,
                HtmlRule::InlineScript,
                sprintf('an %s handler: %s', $name, self::SCRIPT_FIX),
            ),
            self::scripted($htmlAttribute) => new HtmlFinding(
                $line,
                HtmlRule::InlineScript,
                sprintf('a javascript: URL in %s: %s', $name, self::SCRIPT_FIX),
            ),
            default => null,
        };
    }

    /**
     * @param HtmlAttribute $htmlAttribute An attribute.
     *
     * @return bool Whether its value is a `javascript:` URL, read as a browser reads it: case and the
     *              whitespace and control characters it drops do not count.
     */
    private static function scripted(HtmlAttribute $htmlAttribute): bool
    {
        $value = preg_replace('/[\\x00-\\x20]+/', '', $htmlAttribute->value ?? '') ?? '';

        return str_starts_with(strtolower($value), 'javascript:');
    }
}
