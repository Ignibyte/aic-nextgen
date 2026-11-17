<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Coverage;

use AIC\Workflow\Application\Console\ConsoleOutput;
use AIC\Workflow\Application\Make\MadeLedger;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_map;
use function array_sum;
use function hash;
use function in_array;
use function json_encode;
use function ksort;
use function sprintf;
use function str_starts_with;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * `bin/aic code:coverage --made` (#302): per module and per theme, the files
 * git lists there, how many a scaffold made (a row in `.aic/made.jsonl`),
 * and how many of those still hold the bytes it wrote. A file made and then
 * removed is not counted; its row stays in the ledger.
 */
final readonly class MadeCoverage
{
    /**
     * @param CoverageLookup  $coverageLookup  Each file's area, from the index.
     * @param CoverageSources $coverageSources Reads each file.
     * @param MadeLedger      $madeLedger      The ledger.
     * @param ConsoleOutput   $consoleOutput   Columns and the envelope.
     */
    public function __construct(
        private CoverageLookup $coverageLookup,
        private CoverageSources $coverageSources,
        private MadeLedger $madeLedger,
        private ConsoleOutput $consoleOutput = new ConsoleOutput(),
    ) {
    }

    /**
     * @throws WorkflowRefusal When the index or the ledger cannot be read.
     *
     * @return list<string> A total line, then a line per module and theme.
     */
    public function text(): array
    {
        $areas = $this->areas();
        $lines = [sprintf('code:coverage --made — %s.', self::counted(self::total($areas)))];

        foreach ($areas as $area => $counts) {
            $lines[] = sprintf('  %s: %s', $area, self::counted($counts));
        }

        return $lines;
    }

    /**
     * @throws WorkflowRefusal When the index or the ledger cannot be read.
     *
     * @return string The `aic.console/1` envelope, one line.
     */
    public function json(): string
    {
        $areas = $this->areas();
        $rows = [];

        foreach ($areas as $area => $counts) {
            $rows[] = ['area' => $area, 'files' => $counts[0], 'made' => $counts[1], 'unchanged' => $counts[2]];
        }

        $total = self::total($areas);
        $data = json_encode(
            ['areas' => $rows, 'files' => $total[0], 'made' => $total[1], 'unchanged' => $total[2]],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        );

        return $this->consoleOutput->envelope('code:coverage', $data, []);
    }

    /**
     * @throws WorkflowRefusal When the index or the ledger cannot be read.
     *
     * @return array<string, array{int, int, int}> Per module and theme area, its files, made and unchanged, by area.
     */
    private function areas(): array
    {
        $made = [];

        foreach ($this->madeLedger->rows() as $madeRow) {
            $made[$madeRow->path] = [...($made[$madeRow->path] ?? []), $madeRow->content];
        }

        $areas = [];

        foreach ($this->coverageLookup->fileAreas() as $path => $area) {
            if (!str_starts_with($area, 'module:') && !str_starts_with($area, 'theme:')) {
                continue;
            }

            $counts = $areas[$area] ?? [0, 0, 0];
            $areas[$area] = $this->counts($counts, $path, $made[$path] ?? []);
        }

        ksort($areas);

        return $areas;
    }

    /**
     * @param array{int, int, int} $counts An area's counts so far.
     * @param string               $path   One of its files.
     * @param list<string>         $hashes What the makes of it wrote, `xxh128:<hex>` each.
     *
     * @return array{int, int, int} The counts with the file.
     */
    private function counts(array $counts, string $path, array $hashes): array
    {
        if ($hashes === []) {
            return [$counts[0] + 1, $counts[1], $counts[2]];
        }

        $now = 'xxh128:' . hash('xxh128', $this->coverageSources->read($path));
        $unchanged = in_array($now, $hashes, true)
            ? 1
            : 0;

        return [$counts[0] + 1, $counts[1] + 1, $counts[2] + $unchanged];
    }

    /**
     * @param array<string, array{int, int, int}> $areas Each area's counts.
     *
     * @return array{int, int, int} Their sums.
     */
    private static function total(array $areas): array
    {
        return [
            array_sum(array_map(static fn (array $counts): int => $counts[0], $areas)),
            array_sum(array_map(static fn (array $counts): int => $counts[1], $areas)),
            array_sum(array_map(static fn (array $counts): int => $counts[2], $areas)),
        ];
    }

    /**
     * @param array{int, int, int} $counts Files, made and unchanged.
     *
     * @return string `F file(s), M made by a scaffold, U unchanged since`.
     */
    private static function counted(array $counts): string
    {
        return sprintf('%d file(s), %d made by a scaffold, %d unchanged since', $counts[0], $counts[1], $counts[2]);
    }
}
