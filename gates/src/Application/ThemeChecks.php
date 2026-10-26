<?php

declare(strict_types=1);

namespace Gates\Application;

use InvalidArgumentException;

use function str_contains;
use function str_replace;

/**
 * The theme's two autoload lines and PHPCS roots (#153), which the build searches for and prints when one is
 * missing, as it does for a module's (ModuleChecks).
 */
final readonly class ThemeChecks
{
    /** The two namespaces a theme's classes live under, by their folder under `src/` (#167). */
    private const array NAMESPACES = ['src/Components' => 'App\\Theme\\Components', 'src/Layouts' => 'App\\Theme\\Layouts'];

    /**
     * @param string $theme    The configured theme's folder name.
     * @param string $composer The text of the root composer.json.
     * @param string $phpcs    The text of phpcs.xml.dist.
     *
     * @throws InvalidArgumentException When a line is missing, naming the line to add.
     */
    public function check(string $theme, string $composer, string $phpcs): void
    {
        foreach (self::NAMESPACES as $folder => $namespace) {
            $autoload = $this->autoload($theme, $folder, $namespace);
            $phpcsRoot = $this->phpcsRoot($theme, $folder, $namespace);

            if (!str_contains($composer, $autoload)) {
                throw new InvalidArgumentException(
                    'theme ' . $theme . ': add ' . $autoload . ' to autoload.psr-4 in composer.json, then run composer dump-autoload.',
                );
            }

            if (!str_contains($phpcs, $phpcsRoot)) {
                throw new InvalidArgumentException(
                    'theme ' . $theme . ': add ' . $phpcsRoot . ' to the rootNamespaces of TypeNameMatchesFileName in phpcs.xml.dist.',
                );
            }
        }
    }

    /**
     * @param string $theme     The theme's folder name.
     * @param string $folder    `src/Components` or `src/Layouts`.
     * @param string $namespace Its namespace, without a trailing `\`.
     *
     * @return string Its line in composer.json's autoload.psr-4.
     */
    private function autoload(string $theme, string $folder, string $namespace): string
    {
        return '"' . str_replace('\\', '\\\\', $namespace) . '\\\\": "themes/' . $theme . '/' . $folder . '/"';
    }

    /**
     * @param string $theme     The theme's folder name.
     * @param string $folder    `src/Components` or `src/Layouts`.
     * @param string $namespace Its namespace, without a trailing `\`.
     *
     * @return string Its root in phpcs.xml.dist's TypeNameMatchesFileName.
     */
    private function phpcsRoot(string $theme, string $folder, string $namespace): string
    {
        return '<element key="themes/' . $theme . '/' . $folder . '" value="' . $namespace . '"/>';
    }
}
