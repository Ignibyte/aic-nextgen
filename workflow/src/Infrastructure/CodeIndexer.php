<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\CodeIndex;
use AIC\Workflow\Application\RunHost;
use AIC\Workflow\Domain\CodeIndexReport;
use AIC\Workflow\Exception\WorkflowRefusal;
use AIC\Workflow\Parsing\PhpSymbolExtractor;
use FilesystemIterator;
use Override;
use PDO;
use PDOException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

use function array_diff_assoc;
use function array_diff_key;
use function array_filter;
use function array_keys;
use function count;
use function file_get_contents;
use function hash;
use function is_dir;
use function is_file;
use function is_link;
use function microtime;
use function str_ends_with;
use function strlen;
use function substr;

/**
 * Builds the code index (#106, droost-engine's `RepoIndexer`): the files git
 * lists (and, in an app, the installed framework's sources), each hashed with
 * xxh128; only those whose hash changed are parsed again, the ones gone are
 * dropped, and the build's surface is read again when its hash changed. One
 * refresh is one transaction.
 */
final readonly class CodeIndexer implements CodeIndex
{
    use DirectoryEntries;

    /**
     * The installed framework's sources, in an app.
     */
    private const string FRAMEWORK = 'vendor/aic/framework/src';

    /**
     * @param string             $root               The project's root.
     * @param RunHost            $runHost            The files git lists.
     * @param CodeSurfaceGraph   $codeSurfaceGraph   The surface's symbols and edges.
     * @param CodeIndexSchema    $codeIndexSchema    Opens the index.
     * @param CodeAreas          $codeAreas          A path's area.
     * @param PhpSymbolExtractor $phpSymbolExtractor Reads a PHP file.
     */
    public function __construct(
        private string $root,
        private RunHost $runHost,
        private CodeSurfaceGraph $codeSurfaceGraph,
        private CodeIndexSchema $codeIndexSchema = new CodeIndexSchema(),
        private CodeAreas $codeAreas = new CodeAreas(),
        private PhpSymbolExtractor $phpSymbolExtractor = new PhpSymbolExtractor(),
    ) {
    }

    /**
     * @param bool $full Whether to build it whole.
     *
     * @throws WorkflowRefusal When it cannot be built.
     *
     * @return CodeIndexReport What the refresh did.
     */
    #[Override]
    public function refresh(bool $full): CodeIndexReport
    {
        $start = microtime(true);
        $hashes = $this->hashes();
        $pdo = $this->codeIndexSchema->open($this->root, $full);
        $codeIndexStore = new CodeIndexStore($pdo, $this->codeIndexSchema);

        try {
            $pdo->beginTransaction();
            [$parsed, $removed, $broken] = $this->apply($codeIndexStore, $hashes);
            $this->surface($codeIndexStore);
            $pdo->commit();
            [$symbols, $edges] = $codeIndexStore->counts();
        } catch (PDOException $pdoException) {
            $this->rollBack($pdo);

            throw new WorkflowRefusal('the code index could not be written: ' . $pdoException->getMessage(), 0, $pdoException);
        }

        return new CodeIndexReport(count($hashes), $parsed, $removed, $symbols, $edges, microtime(true) - $start, $broken);
    }

    /**
     * @param CodeIndexStore        $codeIndexStore The index.
     * @param array<string, string> $hashes         Each file's hash now.
     *
     * @throws WorkflowRefusal When the index cannot be read.
     * @throws PDOException    When it cannot be written.
     *
     * @return array{int, int, list<string>} How many PHP files were parsed and files removed, and those that did not parse.
     */
    private function apply(CodeIndexStore $codeIndexStore, array $hashes): array
    {
        $stored = $codeIndexStore->files();
        $changed = array_diff_assoc($hashes, $stored);
        $gone = array_diff_key($stored, $hashes);
        $broken = [];

        foreach ($changed as $path => $hash) {
            $broken = $this->index($codeIndexStore, $path, $hash)
                ? $broken
                : [...$broken, $path];
        }

        foreach (array_keys($gone) as $path) {
            $codeIndexStore->remove($path);
        }

        $php = array_filter(array_keys($changed), static fn (string $path): bool => str_ends_with($path, '.php'));

        return [count($php), count($gone), $broken];
    }

    /**
     * @param CodeIndexStore $codeIndexStore The index.
     * @param string         $path           A changed file.
     * @param string         $hash           Its hash.
     *
     * @throws PDOException When it cannot be written.
     *
     * @return bool Whether it parsed (a file that is not PHP always does).
     */
    private function index(CodeIndexStore $codeIndexStore, string $path, string $hash): bool
    {
        $area = $this->codeAreas->area($path);

        if (!str_ends_with($path, '.php')) {
            $codeIndexStore->file($path, $hash, $area, [], []);

            return true;
        }

        $parsedFile = $this->phpSymbolExtractor->extract($path, $area, (string) file_get_contents($this->root . '/' . $path));
        $codeIndexStore->file($path, $hash, $area, $parsedFile->symbols, $parsedFile->edges);

        return $parsedFile->parsed;
    }

    /**
     * @param CodeIndexStore $codeIndexStore The index.
     *
     * @throws WorkflowRefusal When the surface is not a surface manifest.
     * @throws PDOException    When it cannot be written.
     */
    private function surface(CodeIndexStore $codeIndexStore): void
    {
        $hash = $this->codeSurfaceGraph->hash();

        if ($hash === $codeIndexStore->surfaceHash()) {
            return;
        }

        [$symbols, $edges] = $hash === ''
            ? [[], []]
            : $this->codeSurfaceGraph->graph();
        $codeIndexStore->surface($hash, $symbols, $edges);
    }

    /**
     * @throws WorkflowRefusal When git cannot list the files.
     *
     * @return array<string, string> Each file to index, by path, with its xxh128.
     */
    private function hashes(): array
    {
        $hashes = [];

        foreach ([...$this->runHost->changedFiles(null), ...$this->framework()] as $path) {
            $file = $this->root . '/' . $path;
            $hashes = is_file($file) && !is_link($file)
                ? [...$hashes, $path => hash('xxh128', (string) file_get_contents($file))]
                : $hashes;
        }

        return $hashes;
    }

    /**
     * @return list<string> In an app, the installed framework's PHP files; none in this repository, where git lists them.
     */
    private function framework(): array
    {
        $directory = $this->root . '/' . self::FRAMEWORK;

        return !is_dir($directory) || is_dir($this->root . '/framework/src')
            ? []
            : $this->phpUnder($directory);
    }

    /**
     * @param string $directory The installed framework's sources.
     *
     * @return list<string> Its PHP files, as paths in the project.
     */
    private function phpUnder(string $directory): array
    {
        $paths = [];
        $flags = FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS;

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, $flags));

        foreach (self::directoryEntries($files) as $file) {
            if (!str_ends_with($file->getFilename(), '.php')) {
                continue;
            }

            $paths[] = self::FRAMEWORK . substr($file->getPathname(), strlen($directory));
        }

        return $paths;
    }

    /**
     * @param PDO $pdo The index.
     */
    private function rollBack(PDO $pdo): void
    {
        if (!$pdo->inTransaction()) {
            return;
        }

        $pdo->rollBack();
    }
}
