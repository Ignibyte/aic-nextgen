<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\ProcessResult;
use RuntimeException;

use function copy;
use function file_put_contents;
use function implode;
use function in_array;
use function is_dir;
use function is_file;
use function mkdir;
use function unlink;
use function var_export;

use const PHP_EOL;

/**
 * Builds the compiled site (gate:23, `composer run native:serve`): the class
 * bundle in place of the autoloader, then `bootstrap/serve.php`, compiled with
 * `elephc --web` into `<work>/main`. The bundle is the union of every runtime
 * class's closure, dependencies first, through gate:21's bundler.
 */
final readonly class NativeSiteBuilder
{
    /**
     * @param NativeToolchain    $nativeToolchain    The compiler.
     * @param string             $package            aic/gates' directory: its base configs and elephc pins (#116).
     * @param RuntimeClassFinder $runtimeClassFinder Finds the runtime classes.
     * @param ComposerPsr4Loader $composerPsr4Loader Reads the PSR-4 map.
     * @param ClosureBundler     $closureBundler     Orders each class's closure.
     */
    public function __construct(
        private NativeToolchain $nativeToolchain,
        private string $package,
        private RuntimeClassFinder $runtimeClassFinder = new RuntimeClassFinder(),
        private ComposerPsr4Loader $composerPsr4Loader = new ComposerPsr4Loader(),
        private ClosureBundler $closureBundler = new ClosureBundler(),
    ) {
    }

    /**
     * @param string $root    Repo-root absolute path.
     * @param string $workDir Where the program and the binary go.
     *
     * @throws RuntimeException When the work directory or the program cannot be written.
     *
     * @return ProcessResult The package install's failure, or the compile's result.
     */
    public function build(string $root, string $workDir): ProcessResult
    {
        if (!is_dir($workDir) && !mkdir($workDir, 0o755, true)) {
            throw new RuntimeException('native site — cannot create ' . $workDir);
        }

        if (is_file($workDir . '/main')) {
            unlink($workDir . '/main');
        }

        $this->writeProgram($root, $workDir);
        $processResult = $this->nativeToolchain->installPackages($workDir);

        return $processResult->exitCode === 0
            ? $this->nativeToolchain->compileWeb($workDir)
            : $processResult;
    }

    /**
     * @param string $root    Repo-root absolute path.
     * @param string $workDir The work directory.
     *
     * @throws RuntimeException When a file cannot be written or copied.
     */
    private function writeProgram(string $root, string $workDir): void
    {
        $lines = ['<?php', '', 'declare(strict_types=1);', '', '// Every runtime class, dependencies first (gate:23).', ''];

        foreach ($this->bundledFiles($root) as $file) {
            $lines[] = 'require_once ' . var_export($file, true) . ';';
        }

        $main = '<?php' . PHP_EOL . PHP_EOL . 'declare(strict_types=1);' . PHP_EOL . PHP_EOL
            . "require __DIR__ . '/_bundle.php';" . PHP_EOL
            . 'require ' . var_export($root . '/bootstrap/serve.php', true) . ';' . PHP_EOL;
        $written = file_put_contents($workDir . '/_bundle.php', implode(PHP_EOL, $lines) . PHP_EOL) !== false
            && file_put_contents($workDir . '/main.php', $main) !== false
            && copy($this->package . '/native/elephc.toml', $workDir . '/elephc.toml')
            && copy($this->package . '/native/elephc.lock', $workDir . '/elephc.lock');

        if (!$written) {
            throw new RuntimeException('native site — cannot write the program in ' . $workDir);
        }
    }

    /**
     * @param string $root Repo-root absolute path.
     *
     * @throws RuntimeException When a closure cannot be read.
     *
     * @return list<string> Every file of every runtime class's closure, once, dependencies first.
     */
    private function bundledFiles(string $root): array
    {
        $psr4Map = $this->composerPsr4Loader->load($root);
        $files = [];

        foreach ($this->runtimeClassFinder->find($root) as $class) {
            $files = self::union($files, $this->closureBundler->closure($class, $psr4Map));
        }

        return $files;
    }

    /**
     * @param list<string> $files   The files so far.
     * @param list<string> $closure One class's closure, dependencies first.
     *
     * @return list<string> The files with the closure's new ones appended, in its order.
     */
    private static function union(array $files, array $closure): array
    {
        foreach ($closure as $file) {
            if (in_array($file, $files, true)) {
                continue;
            }

            $files[] = $file;
        }

        return $files;
    }
}
