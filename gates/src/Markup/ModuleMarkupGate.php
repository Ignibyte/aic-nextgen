<?php

declare(strict_types=1);

namespace Gates\Markup;

use Gates\Domain\MarkupFinding;

use function count;
use function fwrite;
use function is_dir;
use function sprintf;
use function usort;

use const PHP_EOL;
use const STDERR;
use const STDOUT;

/**
 * gate:32, module markup (#156): a module holds no HTML at all. Every file under `modules/` is checked; the theme
 * holds every template, component and asset, and the Renderer turns components into HTML.
 */
final readonly class ModuleMarkupGate
{
    /**
     * @param ModuleFiles     $moduleFiles     Walks `modules/`.
     * @param FileMarkupCheck $fileMarkupCheck Checks each file.
     */
    public function __construct(
        private ModuleFiles $moduleFiles = new ModuleFiles(),
        private FileMarkupCheck $fileMarkupCheck = new FileMarkupCheck(),
    ) {
    }

    /**
     * @param string   $root   The project's root, or a plant root.
     * @param resource $stdout Where the summary or the deferral goes.
     * @param resource $stderr Where the findings go.
     *
     * @return int The exit code: 0 when no module holds markup, or there are no modules.
     */
    public function run(string $root, mixed $stdout = STDOUT, mixed $stderr = STDERR): int
    {
        if (!is_dir($root . '/modules')) {
            fwrite($stdout, 'gate:32 — no modules/ folder; deferring.' . PHP_EOL);

            return 0;
        }

        [$files, $findings] = $this->findings($root);

        foreach ($findings as $finding) {
            fwrite($stderr, 'gate:32 — ' . $finding->message() . PHP_EOL);
        }

        $summary = sprintf('gate:32 — module markup: %d file(s) under modules/, %d finding(s).', count($files), count($findings));
        fwrite($findings === [] ? $stdout : $stderr, $summary . PHP_EOL);

        return $findings === []
            ? 0
            : 1;
    }

    /**
     * @param string $root The root.
     *
     * @return array{list<string>, list<MarkupFinding>} The files under `modules/`, and every finding in path and line
     *                                                  order.
     */
    private function findings(string $root): array
    {
        [$files, $findings] = $this->moduleFiles->walk($root, 'modules');

        foreach ($files as $file) {
            $findings = [...$findings, ...$this->fileMarkupCheck->check($root, $file)];
        }

        usort($findings, self::order(...));

        return [$files, $findings];
    }

    /**
     * @param MarkupFinding $left  One finding.
     * @param MarkupFinding $right Another.
     *
     * @return int Their order: by path, then by line.
     */
    private static function order(MarkupFinding $left, MarkupFinding $right): int
    {
        return [$left->path, $left->line] <=> [$right->path, $right->line];
    }
}
