<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure\Probe;

use AIC\Workflow\Application\Probe\ProbeFiles;
use AIC\Workflow\Domain\Probe\ProbeApp;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;

use function dirname;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function hash;
use function is_array;
use function is_dir;
use function is_file;
use function is_link;
use function is_readable;
use function is_string;
use function mkdir;
use function pathinfo;
use function preg_replace;
use function strlen;
use function substr;
use function unlink;

use const PATHINFO_EXTENSION;

/**
 * A scenario's files, written as the operator (#119), and the evidence in
 * this repository's `var/probe/<scenario>/` (gitignored with `var/`), JSON
 * and text only.
 */
final readonly class LocalProbeFiles implements ProbeFiles
{
    /**
     * Leave room below the filesystem's usual 255-byte component limit.
     */
    private const int NAME_BYTES = 200;

    /**
     * @param string $evidence This repository's `var/probe`, absolute.
     */
    public function __construct(
        private string $evidence,
    ) {
    }

    /**
     * @param ProbeApp $probeApp The app.
     * @param string   $path     A path in it.
     *
     * @return string|null Its bytes; null when it is not a readable file.
     */
    #[Override]
    public function read(ProbeApp $probeApp, string $path): ?string
    {
        $absolute = $probeApp->root . '/' . $path;

        return is_file($absolute) && is_readable($absolute)
            ? (string) file_get_contents($absolute)
            : null;
    }

    /**
     * @param ProbeApp $probeApp The app.
     * @param string   $path     A path in it.
     * @param string   $bytes    What it holds.
     *
     * @return bool Whether it was written.
     */
    #[Override]
    public function write(ProbeApp $probeApp, string $path, string $bytes): bool
    {
        return $this->put($probeApp->root . '/' . $path, $bytes);
    }

    /**
     * @param ProbeApp $probeApp The app.
     * @param string   $path     A file in it.
     *
     * @return bool Whether it was there and is gone.
     */
    #[Override]
    public function delete(ProbeApp $probeApp, string $path): bool
    {
        $absolute = $probeApp->root . '/' . $path;

        return (is_file($absolute) || is_link($absolute)) && unlink($absolute);
    }

    /**
     * @param string $scenario The scenario.
     * @param string $file     The evidence's name; anything but letters, digits, `.` and `-` becomes `-`.
     * @param string $bytes    What it holds.
     *
     * @throws WorkflowRefusal When the evidence cannot be written completely.
     */
    #[Override]
    public function evidence(string $scenario, string $file, string $bytes): void
    {
        $path = $this->evidence . '/' . $scenario . '/' . $this->name($file);

        if (!$this->put($path, $bytes)) {
            throw new WorkflowRefusal('probe evidence could not be written completely to ' . $path . '.');
        }
    }

    /**
     * @param string $scenario The scenario.
     */
    #[Override]
    public function fresh(string $scenario): void
    {
        $files = glob($this->evidence . '/' . $scenario . '/*');

        foreach (is_array($files) ? $files : [] as $file) {
            if (!is_file($file)) {
                continue;
            }

            unlink($file);
        }
    }

    /**
     * @param string $file The complete evidence label.
     *
     * @return string Its sanitized name, bounded with the complete label's hash when long.
     */
    private function name(string $file): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9.-]+/', '-', $file);
        $name = is_string($sanitized)
            ? $sanitized
            : 'evidence';

        if (strlen($name) <= self::NAME_BYTES) {
            return $name;
        }

        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $suffix = $extension !== '' && strlen($extension) <= 16
            ? '.' . $extension
            : '';

        return substr($name, 0, self::NAME_BYTES - 65 - strlen($suffix)) . '-' . hash('sha256', $file) . $suffix;
    }

    /**
     * @param string $absolute A file.
     * @param string $bytes    What it holds.
     *
     * @return bool Whether it was written, its folder made.
     */
    private function put(string $absolute, string $bytes): bool
    {
        $folder = dirname($absolute);

        return (is_dir($folder) || mkdir($folder, 0o775, true)) && file_put_contents($absolute, $bytes) === strlen($bytes);
    }
}
