<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Workflow\Application\Make\MakeNames;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_diff;
use function array_filter;
use function array_keys;
use function array_map;
use function array_slice;
use function array_values;
use function count;
use function file_get_contents;
use function fwrite;
use function glob;
use function implode;
use function is_file;
use function sort;
use function strlen;
use function substr;

use const ARRAY_FILTER_USE_BOTH;
use const GLOB_ONLYDIR;
use const PHP_EOL;
use const STDERR;
use const STDOUT;

/**
 * gate:31 (#122): `modules/scaffold/` is what the make commands make. It runs
 * `ScaffoldSequence` through the real make commands over `ScaffoldFiles`,
 * which hides the committed module and keeps every write in memory, then
 * compares the made files with the committed ones byte for byte, both ways,
 * including every authored Template-suffixed file; compiled output lives under generated/. It also
 * holds the lines `make:module` prints to the ones the build asks for. A
 * finding prints how to make the module again. AIC only: an app has no
 * `modules/scaffold/`.
 */
final readonly class MakeScaffoldGate
{
    /**
     * How to make the module again.
     */
    private const string REGENERATE = 'to make it again: rm -rf modules/scaffold themes/app/components/Scaffold*; composer run build; '
        . 'run the ten lines of Gates\\Application\\ScaffoldSequence with bin/aic; composer run build.';

    /**
     * @param ScaffoldFiles          $scaffoldFiles The overlay the commands write to.
     * @param array<string, Command> $commands      The make commands, built over it.
     * @param ModuleChecks           $moduleChecks  The lines the build asks for.
     * @param MakeNames              $makeNames     The lines `make:module` prints.
     * @param FileLister             $fileLister    Lists the committed module.
     */
    public function __construct(
        private ScaffoldFiles $scaffoldFiles,
        private array $commands,
        private ModuleChecks $moduleChecks = new ModuleChecks(),
        private MakeNames $makeNames = new MakeNames(),
        private FileLister $fileLister = new FileLister(),
    ) {
    }

    /**
     * @param string   $root   The project's root.
     * @param resource $stdout Where the summary goes.
     * @param resource $stderr Where each finding goes.
     *
     * @throws WorkflowRefusal When scaffold naming fails.
     *
     * @return int 0 when the committed module is what the makes make, 1 otherwise.
     */
    public function run(string $root, mixed $stdout = STDOUT, mixed $stderr = STDERR): int
    {
        $findings = [...$this->sequence(), ...$this->lines()];
        $findings = $findings === []
            ? $this->compare($root)
            : $findings;

        foreach ($findings as $finding) {
            fwrite($stderr, 'gate:31 — ' . $finding . PHP_EOL);
        }

        if ($findings !== []) {
            fwrite($stderr, 'gate:31 — ' . self::REGENERATE . PHP_EOL);

            return 1;
        }

        fwrite($stdout, 'gate:31 — modules/scaffold/ and its theme components are what the ' . count(ScaffoldSequence::LINES)
            . ' make lines make: '
            . count($this->scaffoldFiles->made()) . ' file(s), byte for byte.' . PHP_EOL);

        return 0;
    }

    /**
     * @return list<string> Each line that did not exit 0, with what it said.
     */
    private function sequence(): array
    {
        $findings = [];

        foreach (ScaffoldSequence::LINES as $line) {
            $collectedLines = new CollectedLines();
            $name = $line[0];
            $command = $this->commands[$name] ?? null;
            $exit = $command instanceof Command
                ? $command->command(new CommandInput(array_slice($line, 1)))->writeTo($collectedLines)
                : 127;

            if ($exit === 0) {
                continue;
            }

            $findings[] = 'bin/aic ' . implode(' ', $line) . ' exits ' . $exit . ': ' . implode(' ', $collectedLines->lines());
        }

        return $findings;
    }

    /**
     * @throws WorkflowRefusal When scaffold naming fails.
     *
     * @return list<string> Each of `make:module`'s printed lines for `scaffold` that is not the build's.
     */
    private function lines(): array
    {
        $namespace = $this->makeNames->module('scaffold');
        $autoload = $this->makeNames->autoload('scaffold', $namespace);
        $phpcsRoot = $this->makeNames->phpcsRoot('scaffold', $namespace);
        $findings = [];

        if ($autoload !== $this->moduleChecks->autoload('scaffold', $namespace . '\\')) {
            $findings[] = 'make:module prints a composer.json line the build does not ask for: ' . $autoload;
        }

        if ($phpcsRoot !== $this->moduleChecks->phpcsRoot('scaffold', $namespace . '\\')) {
            $findings[] = 'make:module prints a phpcs.xml.dist line the build does not ask for: ' . $phpcsRoot;
        }

        return $findings;
    }

    /**
     * @param string $root The project's root.
     *
     * @return list<string> Each file made but not committed, committed but not made, or different.
     */
    private function compare(string $root): array
    {
        $made = $this->scaffoldFiles->made();
        $committed = $this->committed($root);
        $differs = array_filter(
            $made,
            static fn (string $bytes, string $path): bool => is_file($root . '/' . $path)
                && file_get_contents($root . '/' . $path) !== $bytes,
            ARRAY_FILTER_USE_BOTH,
        );

        return [
            ...$this->found(array_diff(array_keys($made), $committed), ' is made but not committed.'),
            ...$this->found(array_diff($committed, array_keys($made)), ' is committed but the makes do not make it.'),
            ...$this->found(array_keys($differs), ' differs from what the makes make.'),
        ];
    }

    /**
     * @param array<int, string> $paths Paths with one finding.
     * @param string             $what  The finding, after the path.
     *
     * @return list<string> One line per path.
     */
    private function found(array $paths, string $what): array
    {
        return array_values(array_map(static fn (string $path): string => $path . $what, $paths));
    }

    /**
     * @param string $root The project's root.
     *
     * @return list<string> Every authored module file, sorted; compiled classes live under generated/.
     */
    private function committed(string $root): array
    {
        $components = glob($root . '/' . ScaffoldFiles::THEME . '*', GLOB_ONLYDIR);
        $folders = [ScaffoldFiles::FOLDER, ...array_map(
            static fn (string $folder): string => substr($folder, strlen($root) + 1) . '/',
            $components === false ? [] : $components,
        )];
        $files = [];

        foreach ($folders as $folder) {
            foreach ($this->fileLister->files($root . '/' . $folder) as $file) {
                $files[] = $folder . substr($file, strlen($root . '/' . $folder));
            }
        }

        sort($files);

        return $files;
    }
}
