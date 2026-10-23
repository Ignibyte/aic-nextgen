<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\MarkdownDocument;
use Gates\Domain\RegexCaptures;

use function array_filter;
use function class_exists;
use function count;
use function enum_exists;
use function in_array;
use function interface_exists;
use function is_dir;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;
use function trait_exists;

/**
 * gate:29's name check (D6): every `AIC\…` name in a page's code is a class,
 * an interface, an enum, a trait or a namespace folder; every `gate:N` the page
 * names is a gate; every `composer run <script>` is a script.
 */
final readonly class DocsNameChecks
{
    use RegexCaptures;

    /**
     * The namespace roots, longest prefix first, as composer.json maps them.
     */
    private const array ROOTS = [
        'AIC\\' => 'framework/src/',
        'AIC\\Workflow\\' => 'workflow/src/',
    ];

    /**
     * @param string       $root    The repo root.
     * @param list<string> $scripts composer.json's script names.
     */
    public function __construct(private string $root, private array $scripts)
    {
    }

    /**
     * @param MarkdownDocument $markdownDocument A page.
     *
     * @return array{list<string>, int} A finding per name that does not resolve, and how many names were checked.
     */
    public function check(MarkdownDocument $markdownDocument): array
    {
        [$nameFindings, $names] = $this->names($markdownDocument);
        [$scriptFindings, $scripts] = $this->scriptLines($markdownDocument);

        return [[...$nameFindings, ...$scriptFindings], $names + $scripts];
    }

    /**
     * @param MarkdownDocument $markdownDocument A page.
     *
     * @return array{list<string>, int} A finding per `AIC\…` name in code that does not resolve, and how many were checked.
     */
    private function names(MarkdownDocument $markdownDocument): array
    {
        $findings = [];
        $checked = 0;

        foreach ($markdownDocument->code() as [$line, $span]) {
            $names = self::regexColumn('/AIC(?:\\\\\\w+)+/', $span, 0);
            $checked += count($names);

            foreach (array_filter($names, fn (string $name): bool => !$this->resolves($name)) as $name) {
                $findings[] = sprintf(
                    '%s:%d: %s is no class, interface, enum, trait or namespace; fix the name.',
                    $markdownDocument->page,
                    $line,
                    $name,
                );
            }
        }

        return [$findings, $checked];
    }

    /**
     * @param MarkdownDocument $markdownDocument A page.
     *
     * @return array{list<string>, int} A finding per `gate:N` or `composer run` script that does not exist, and how many were checked.
     */
    private function scriptLines(MarkdownDocument $markdownDocument): array
    {
        $findings = [];
        $checked = 0;

        foreach ($markdownDocument->lines() as $line => $text) {
            [$scripts, $missing] = $this->scripts($text);
            $checked += $scripts;

            foreach ($missing as $script) {
                $findings[] = sprintf('%s:%d: %s is not a composer.json script; fix the name.', $markdownDocument->page, $line, $script);
            }
        }

        return [$findings, $checked];
    }

    /**
     * @param string $text One line.
     *
     * @return array{int, list<string>} How many gates and scripts the line names, and the ones that do not exist.
     */
    private function scripts(string $text): array
    {
        $gates = self::regexColumn('/(?<![\\w:-])gate:\\d+\\b/', $text, 0);
        $runs = self::regexColumn('/composer run ([\\w:-]+)/', $text, 1);
        $missing = [];

        foreach ([...$gates, ...$runs] as $script) {
            if (in_array($script, $this->scripts, true)) {
                continue;
            }

            $missing[] = $script;
        }

        return [count($gates) + count($runs), $missing];
    }

    /**
     * @param string $name An `AIC\…` name.
     *
     * @return bool Whether it is a symbol the autoloader finds or a namespace folder.
     */
    private function resolves(string $name): bool
    {
        return class_exists($name) || interface_exists($name) || enum_exists($name) || trait_exists($name) || $this->isNamespace($name);
    }

    /**
     * @param string $name An `AIC\…` name.
     *
     * @return bool Whether it names a namespace folder under its package's source root.
     */
    private function isNamespace(string $name): bool
    {
        foreach (self::ROOTS as $prefix => $folder) {
            if (!str_starts_with($name . '\\', $prefix)) {
                continue;
            }

            return is_dir($this->root . '/' . $folder . str_replace('\\', '/', substr($name . '\\', strlen($prefix))));
        }

        return false;
    }
}
