<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Module\Contracts\ModuleManifest;
use Gates\Domain\ClassKinds;
use Gates\Domain\MarkdownDocument;
use ReflectionClass;
use ReflectionMethod;

use function array_diff;
use function array_keys;
use function basename;
use function count;
use function fwrite;
use function glob;
use function is_array;
use function json_decode;
use function preg_match;
use function sprintf;

use const GLOB_ONLYDIR;
use const PHP_EOL;
use const STDERR;
use const STDOUT;

/**
 * gate:29 — the architecture docs hold to the code. The pages in
 * `framework/docs/` are hand-written; this gate compares each inventory they
 * state with what the code has (the gates, the entry kinds, the module folders,
 * the namespaces, the manifest's declarations, every `AIC\…` name and anchor)
 * and holds their prose to the mechanical part of the no-ai-slop standard.
 */
final readonly class ArchitectureDocsGate
{
    /**
     * The pages the checks read, beside any others the README lists.
     */
    private const array REQUIRED = ['README.md', 'kinds.md', 'systems.md', 'modules.md', 'gates.md'];

    /**
     * @param QuietFileReader     $quietFileReader     Reads the pages and the manifests.
     * @param DocsInventoryChecks $docsInventoryChecks D1, D2, D4.
     * @param DocsKindChecks      $docsKindChecks      D3, D5.
     * @param DocsAnchorChecks    $docsAnchorChecks    D7.
     * @param DocsWritingChecks   $docsWritingChecks   D9.
     * @param ClassKinds          $classKinds          The entry kinds and the module folders.
     */
    public function __construct(
        private QuietFileReader $quietFileReader = new QuietFileReader(),
        private DocsInventoryChecks $docsInventoryChecks = new DocsInventoryChecks(),
        private DocsKindChecks $docsKindChecks = new DocsKindChecks(),
        private DocsAnchorChecks $docsAnchorChecks = new DocsAnchorChecks(),
        private DocsWritingChecks $docsWritingChecks = new DocsWritingChecks(),
        private ClassKinds $classKinds = new ClassKinds(),
    ) {
    }

    /**
     * @param string   $root   The repo root.
     * @param resource $stdout Where the summary goes.
     * @param resource $stderr Where the findings go.
     *
     * @return int The exit code: 0 when the docs hold to the code.
     */
    public function run(string $root, mixed $stdout = STDOUT, mixed $stderr = STDERR): int
    {
        $pages = $this->pages($root);

        if (self::missing($pages, $stderr)) {
            return 1;
        }

        $scripts = $this->scripts($root);
        [$findings, $names] = $this->pageFindings($root, $pages, $scripts);
        $findings = [...$this->inventoryFindings($root, $pages, $scripts), ...$findings];

        foreach ($findings as $finding) {
            fwrite($stderr, 'gate:29 — ' . $finding . PHP_EOL);
        }

        fwrite($findings === [] ? $stdout : $stderr, $this->summary($root, $pages, $scripts, $names, count($findings)) . PHP_EOL);

        return $findings === []
            ? 0
            : 1;
    }

    /**
     * @param string                          $root     The repo root.
     * @param array<string, MarkdownDocument> $pages    Every page, by file name.
     * @param list<string>                    $scripts  composer.json's script names.
     * @param int                             $names    The names D6 checked.
     * @param int                             $findings How many findings there are.
     *
     * @return string The summary line: what was checked, and the findings.
     */
    private function summary(string $root, array $pages, array $scripts, int $names, int $findings): string
    {
        return sprintf(
            'gate:29 — architecture docs: %d page(s), %d gate(s), %d entry kind(s), %d namespace(s), %d name(s) checked, %d finding(s).',
            count($pages),
            count(self::gateNumbers($scripts)),
            count($this->classKinds->entryKinds()),
            count(self::folders($root)),
            $names,
            $findings,
        );
    }

    /**
     * @param string                          $root    The repo root.
     * @param array<string, MarkdownDocument> $pages   Every page, by file name.
     * @param list<string>                    $scripts composer.json's script names.
     *
     * @return list<string> The findings of D1 to D5.
     */
    private function inventoryFindings(string $root, array $pages, array $scripts): array
    {
        return [
            ...$this->docsInventoryChecks->unlinked($pages['README.md'], array_keys($pages)),
            ...$this->docsInventoryChecks->gates($pages['gates.md'], self::gateNumbers($scripts), $this->constitutionRows($root)),
            ...$this->docsInventoryChecks->namespaces($pages['systems.md'], self::folders($root)),
            ...$this->docsKindChecks->kinds($pages['kinds.md'], $this->classKinds->entryKinds()),
            ...$this->docsKindChecks->folders($pages['modules.md'], ClassKinds::FOLDERS),
            ...$this->docsKindChecks->declarations($pages['modules.md'], self::declarations()),
        ];
    }

    /**
     * @param string                          $root    The repo root.
     * @param array<string, MarkdownDocument> $pages   Every page, by file name.
     * @param list<string>                    $scripts composer.json's script names.
     *
     * @return array{list<string>, int} The findings of D6, D7 and D9 over every page, and the names D6 checked.
     */
    private function pageFindings(string $root, array $pages, array $scripts): array
    {
        $docsNameChecks = new DocsNameChecks($root, $scripts);
        $findings = [];
        $names = 0;

        foreach ($pages as $page) {
            [$found, $checked] = $docsNameChecks->check($page);
            $names += $checked;
            $findings = [
                ...$findings,
                ...$found,
                ...$this->docsAnchorChecks->check($page, $pages),
                ...$this->docsWritingChecks->check($page),
            ];
        }

        return [$findings, $names];
    }

    /**
     * @param string $root The repo root.
     *
     * @return array<string, MarkdownDocument> Every readable page in `framework/docs/`, by file name.
     */
    private function pages(string $root): array
    {
        $pages = [];
        $files = glob($root . '/framework/docs/*.md');

        foreach ($files === false ? [] : $files as $file) {
            $contents = $this->quietFileReader->read($file);

            if ($contents === null) {
                continue;
            }

            $pages[basename($file)] = new MarkdownDocument(basename($file), $contents);
        }

        return $pages;
    }

    /**
     * @param string $root The repo root.
     *
     * @return list<string> composer.json's script names.
     */
    private function scripts(string $root): array
    {
        $composer = json_decode($this->quietFileReader->read($root . '/composer.json') ?? '', true);
        $names = [];

        if (!is_array($composer) || !is_array($composer['scripts'] ?? null)) {
            return $names;
        }

        foreach (array_keys($composer['scripts']) as $name) {
            $names[] = (string) $name;
        }

        return $names;
    }

    /**
     * @param string $root The repo root.
     *
     * @return list<int> The gate numbers CONSTITUTION.md's §0 table lists.
     */
    private function constitutionRows(string $root): array
    {
        $markdownDocument = new MarkdownDocument('CONSTITUTION.md', $this->quietFileReader->read($root . '/CONSTITUTION.md') ?? '');
        $rows = [];

        foreach ($markdownDocument->firstCells() as [, $cell]) {
            if (preg_match('/^\\d+$/', $cell) !== 1) {
                continue;
            }

            $rows[] = (int) $cell;
        }

        return $rows;
    }

    /**
     * @param array<string, MarkdownDocument> $pages  Every page, by file name.
     * @param resource                        $stderr Where the missing pages are named.
     *
     * @return bool Whether a page the checks read is missing.
     */
    private static function missing(array $pages, mixed $stderr): bool
    {
        $missing = array_diff(self::REQUIRED, array_keys($pages));

        foreach ($missing as $page) {
            fwrite($stderr, 'gate:29 — framework/docs/' . $page . ' is missing or unreadable; write it.' . PHP_EOL);
        }

        return $missing !== [];
    }

    /**
     * @param list<string> $scripts composer.json's script names.
     *
     * @return list<int> The gate numbers among them.
     */
    private static function gateNumbers(array $scripts): array
    {
        $numbers = [];

        foreach ($scripts as $script) {
            if (preg_match('/^gate:(\\d+)$/', $script, $match) !== 1) {
                continue;
            }

            $numbers[] = (int) $match[1];
        }

        return $numbers;
    }

    /**
     * @param string $root The repo root.
     *
     * @return list<string> The namespace folders under `framework/src/`.
     */
    private static function folders(string $root): array
    {
        $folders = glob($root . '/framework/src/*', GLOB_ONLYDIR);
        $names = [];

        foreach ($folders === false ? [] : $folders as $folder) {
            $names[] = basename($folder);
        }

        return $names;
    }

    /**
     * @return list<string> The module manifest's declaration methods: every public method but the constructor.
     */
    private static function declarations(): array
    {
        $methods = [];

        foreach ((new ReflectionClass(ModuleManifest::class))->getMethods(ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
            if ($reflectionMethod->isConstructor()) {
                continue;
            }

            $methods[] = $reflectionMethod->getName();
        }

        return $methods;
    }
}
