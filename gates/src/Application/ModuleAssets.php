<?php

declare(strict_types=1);

namespace Gates\Application;

use InvalidArgumentException;

use function array_diff;
use function array_values;
use function basename;
use function implode;
use function in_array;
use function is_dir;
use function pathinfo;
use function preg_match;
use function scandir;
use function sort;

use const PATHINFO_EXTENSION;

/**
 * Lists one assets folder for the bundle (#95): a module's `assets/` or the
 * site theme's. Until #55 a folder is flat and holds only what the compiled
 * site's `/assets/{dir}/{file}` route can serve and type: a lower-case name of
 * letters, digits, dots, dashes and underscores, with an extension
 * `RequestedFile` knows.
 */
final readonly class ModuleAssets
{
    /**
     * The extensions the compiled site serves with their own media type (`RequestedFile::mediaType()`).
     */
    public const array EXTENSIONS = ['css', 'js', 'map', 'txt', 'svg', 'png', 'woff2'];

    /**
     * A file name `RequestedFile` serves as given.
     */
    private const string NAME = '/^[a-z0-9][a-z0-9._-]*$/';

    /**
     * @param string $directory An assets folder, absolute.
     * @param string $relative  The same folder relative to the repo root, for the refusals.
     *
     * @throws InvalidArgumentException When it holds a subfolder, a name outside the pattern or an untyped extension.
     *
     * @return list<string> Its files' names, sorted; none when the folder does not exist.
     */
    public function files(string $directory, string $relative): array
    {
        $entries = is_dir($directory)
            ? scandir($directory)
            : [];
        $files = array_values(array_diff($entries === false ? [] : $entries, ['.', '..']));

        foreach ($files as $file) {
            $this->check($directory . '/' . $file, $relative . '/' . $file);
        }

        sort($files);

        return $files;
    }

    /**
     * @param string $file     An entry, absolute.
     * @param string $relative The same entry relative to the repo root.
     *
     * @throws InvalidArgumentException When it is a folder, its name is outside the pattern, or its extension is untyped.
     */
    private function check(string $file, string $relative): void
    {
        if (is_dir($file)) {
            throw new InvalidArgumentException($relative . ' is a folder; an assets folder is flat until #55.');
        }

        if (preg_match(self::NAME, basename($file)) !== 1) {
            throw new InvalidArgumentException(
                $relative . ': name an asset with a-z, 0-9, ".", "-" and "_", starting with a letter or digit.',
            );
        }

        if (!in_array(pathinfo($file, PATHINFO_EXTENSION), self::EXTENSIONS, true)) {
            throw new InvalidArgumentException($relative . ': an asset is one of ' . implode(', ', self::EXTENSIONS) . ' until #55.');
        }
    }
}
