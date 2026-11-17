<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Workflow\Domain\Scaffold\LoadedScaffold;
use Gates\Domain\ProcessRunner;
use Gates\Domain\ProjectCopier;
use Gates\Domain\ScaffoldCache;
use RuntimeException;

use function array_slice;
use function count;
use function explode;
use function hash;
use function hash_file;
use function implode;
use function in_array;
use function is_file;
use function sort;
use function strlen;
use function substr;
use function trim;

/**
 * gate:33's proof that each scaffold makes gate-clean code (#300): the kind is
 * made with its probe values into a copy of the project, in `site` when the
 * app has it (else its first module), the copy is built, and every gate that
 * reads module code judges it there. A kind whose folder and judges are what
 * last passed is cached and not made again.
 */
final readonly class ScaffoldRenders
{
    /**
     * The gates that judge what a scaffold makes: style, analysis, architecture, the native probe, the build's
     * manifests, templates, module boundaries, raw HTML and module markup.
     */
    public const array JUDGES = [6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 21, 24, 25, 26, 27, 32];

    /**
     * @param ProcessRunner $processRunner Runs the make, the build and each gate in the copy.
     * @param ProjectCopier $projectCopier Makes and removes the copy.
     * @param ScaffoldCache $scaffoldCache What passed before.
     * @param FileLister    $fileLister    Lists a kind's folder for its key.
     */
    public function __construct(
        private ProcessRunner $processRunner,
        private ProjectCopier $projectCopier,
        private ScaffoldCache $scaffoldCache,
        private FileLister $fileLister = new FileLister(),
    ) {
    }

    /**
     * @param string                $root    The project's root.
     * @param array<string, string> $folders Each kind's folder, absolute, by kind.
     * @param list<LoadedScaffold>  $kinds   The kinds that load.
     * @param list<string>          $modules The project's modules.
     *
     * @return array{list<string>, list<string>} Each failure, then a line per kind.
     */
    public function judge(string $root, array $folders, array $kinds, array $modules): array
    {
        $failures = [];
        $lines = [];

        foreach ($kinds as $kind) {
            [$found, $line] = $this->kind($root, $folders, $kind, self::module($modules));
            $failures = [...$failures, ...$found];
            $lines[] = $line;
        }

        return [$failures, $lines];
    }

    /**
     * @param string                $root           The project's root.
     * @param array<string, string> $folders        Each kind's folder, absolute, by kind.
     * @param LoadedScaffold        $loadedScaffold A kind that loads.
     * @param string                $module         The module it is made in.
     *
     * @return array{list<string>, string} What failed, and its line.
     */
    private function kind(string $root, array $folders, LoadedScaffold $loadedScaffold, string $module): array
    {
        $kind = $loadedScaffold->scaffoldManifest->kind;
        $key = $this->key($folders[$kind] ?? '');

        if ($this->scaffoldCache->passed($kind, $key)) {
            return [[], $kind . ': cached, unchanged since it last passed.'];
        }

        $found = $this->made($root, $loadedScaffold, $module);

        if ($found !== []) {
            return [$found, $kind . ': failed; when the project passes these gates on its own, the scaffold is the cause.'];
        }

        $this->scaffoldCache->record($kind, $key);

        return [[], $kind . ': made in ' . $module . ' and judged by gates ' . implode(', ', self::JUDGES) . '.'];
    }

    /**
     * @param string         $root           The project's root.
     * @param LoadedScaffold $loadedScaffold A kind.
     * @param string         $module         The module it is made in.
     *
     * @return list<string> What failed, each naming the kind.
     */
    private function made(string $root, LoadedScaffold $loadedScaffold, string $module): array
    {
        $kind = $loadedScaffold->scaffoldManifest->kind;

        try {
            $copy = $this->projectCopier->copy($root);
        } catch (RuntimeException $runtimeException) {
            return [$kind . ': ' . $runtimeException->getMessage()];
        }

        $this->processRunner->run(['rm', '-f', '--', $copy . '/config/workflow.php']);
        $failures = $this->steps($copy, $loadedScaffold, $module);
        $this->projectCopier->remove($copy);

        return $failures;
    }

    /**
     * @param string         $copy           The copy's root.
     * @param LoadedScaffold $loadedScaffold A kind.
     * @param string         $module         The module it is made in.
     *
     * @return list<string> The first step that failed, or each judging gate that failed.
     */
    private function steps(string $copy, LoadedScaffold $loadedScaffold, string $module): array
    {
        $kind = $loadedScaffold->scaffoldManifest->kind;

        foreach ([self::make($loadedScaffold, $module), ['php', 'vendor/bin/aic-build']] as $step) {
            $failed = $this->failed($step, $copy);

            if ($failed !== '') {
                return [$kind . ': ' . implode(' ', $step) . ' ' . $failed];
            }
        }

        return $this->judged($copy, $kind);
    }

    /**
     * @param string $copy The copy's root, built with the kind made.
     * @param string $kind The kind.
     *
     * @return list<string> Each judging gate that fails there.
     */
    private function judged(string $copy, string $kind): array
    {
        $failures = [];

        foreach (self::JUDGES as $number) {
            $failed = $this->failed(['php', 'vendor/bin/aic-gate', (string) $number], $copy);
            $failures = $failed === ''
                ? $failures
                : [...$failures, $kind . ': gate:' . $number . ' on what it makes ' . $failed];
        }

        return $failures;
    }

    /**
     * @param list<string> $command A command.
     * @param string       $copy    Where it runs.
     *
     * @return string `exits N: <its last lines>` when it fails; empty when it exits 0.
     */
    private function failed(array $command, string $copy): string
    {
        $processResult = $this->processRunner->run($command, $copy);

        return $processResult->exitCode === 0
            ? ''
            : 'exits ' . $processResult->exitCode . ': ' . self::tail($processResult->stdout . $processResult->stderr);
    }

    /**
     * @param string $folder A kind's folder, absolute.
     *
     * @return string The sha256 over its files' paths and hashes and the judging gates.
     */
    private function key(string $folder): string
    {
        $files = $this->fileLister->files($folder);
        sort($files);
        $lines = [implode(',', self::JUDGES)];

        foreach ($files as $file) {
            $hash = is_file($file)
                ? hash_file('sha256', $file)
                : false;
            $lines[] = substr($file, strlen($folder)) . "\0" . ($hash === false ? '' : $hash);
        }

        return hash('sha256', implode("\n", $lines));
    }

    /**
     * @param LoadedScaffold $loadedScaffold A kind.
     * @param string         $module         The module it is made in.
     *
     * @return list<string> `php bin/aic make <kind> <module> <Name>` with each other probe value as a flag.
     */
    private static function make(LoadedScaffold $loadedScaffold, string $module): array
    {
        $manifest = $loadedScaffold->scaffoldManifest;
        $flags = [];

        foreach ($manifest->probe as $name => $value) {
            $flags = $name === 'name'
                ? $flags
                : [...$flags, '--' . $name . '=' . $value];
        }

        return ['php', 'bin/aic', 'make', $manifest->kind, $module, $manifest->probe['name'] ?? '', ...$flags];
    }

    /**
     * @param list<string> $modules The project's modules.
     *
     * @return string `site` when it is there, else the first module; empty when there is none.
     */
    private static function module(array $modules): string
    {
        return in_array('site', $modules, true)
            ? 'site'
            : $modules[0] ?? '';
    }

    /**
     * @param string $output What a step printed.
     *
     * @return string Its last five lines, on one line.
     */
    private static function tail(string $output): string
    {
        $lines = explode("\n", trim($output));

        return implode(' | ', array_slice($lines, count($lines) > 5 ? -5 : 0));
    }
}
