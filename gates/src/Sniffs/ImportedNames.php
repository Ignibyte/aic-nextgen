<?php

declare(strict_types=1);

namespace Gates\Sniffs;

use PHP_CodeSniffer\Files\File;

use function is_int;
use function preg_match;
use function str_starts_with;
use function strlen;
use function strrchr;
use function strstr;
use function substr;
use function trim;

use const T_NAMESPACE;
use const T_OPEN_CURLY_BRACKET;
use const T_SEMICOLON;
use const T_USE;

/**
 * A class name as a file writes it, resolved to its full name through the
 * file's namespace and its `use` imports before the class (#56). Group and
 * function imports are not read: AIC writes one class per `use`.
 */
final readonly class ImportedNames
{
    /**
     * @param File   $phpcsFile    The file.
     * @param string $name         A name as written, such as `Page`, `Contracts\Page` or `\AIC\Controller\Contracts\Page`.
     * @param int    $classPointer The class keyword the name belongs to; imports are read before it.
     *
     * @return string The full name, without a leading backslash.
     */
    public function resolve(File $phpcsFile, string $name, int $classPointer): string
    {
        if (str_starts_with($name, '\\')) {
            return substr($name, 1);
        }

        $first = strstr($name, '\\', true);
        $head = $first === false
            ? $name
            : $first;
        $imported = $this->imports($phpcsFile, $classPointer)[$head] ?? '';

        if ($imported !== '') {
            return $imported . substr($name, strlen($head));
        }

        $namespace = $this->namespace($phpcsFile, $classPointer);

        return $namespace === ''
            ? $name
            : $namespace . '\\' . $name;
    }

    /**
     * @param File $phpcsFile    The file.
     * @param int  $classPointer The class keyword.
     *
     * @return array<string, string> Each imported class's full name by the name the file uses for it.
     */
    private function imports(File $phpcsFile, int $classPointer): array
    {
        $imports = [];
        $use = $phpcsFile->findNext(T_USE, 0, $classPointer);

        while (is_int($use)) {
            $end = $phpcsFile->findNext(T_SEMICOLON, $use, $classPointer);

            if (!is_int($end)) {
                break;
            }

            $imports += self::import(trim($phpcsFile->getTokensAsString($use + 1, $end - $use - 1)));
            $use = $phpcsFile->findNext(T_USE, $end, $classPointer);
        }

        return $imports;
    }

    /**
     * @param File $phpcsFile    The file.
     * @param int  $classPointer The class keyword.
     *
     * @return string The file's namespace before the class; empty for the global one.
     */
    private function namespace(File $phpcsFile, int $classPointer): string
    {
        $namespace = $phpcsFile->findNext(T_NAMESPACE, 0, $classPointer);

        if (!is_int($namespace)) {
            return '';
        }

        $end = $phpcsFile->findNext([T_SEMICOLON, T_OPEN_CURLY_BRACKET], $namespace);

        return is_int($end)
            ? trim($phpcsFile->getTokensAsString($namespace + 1, $end - $namespace - 1))
            : '';
    }

    /**
     * @param string $statement A `use` statement's body, such as `AIC\Controller\Contracts\Page` or `A\B as C`.
     *
     * @return array<string, string> The full name by its alias; empty for a function, constant or group import.
     */
    private static function import(string $statement): array
    {
        if (preg_match('/^\\\\?([A-Za-z_][\\w\\\\]*)(?:\\s+as\\s+(\\w+))?$/i', $statement, $match) !== 1) {
            return [];
        }

        $last = strrchr('\\' . $match[1], '\\');
        $alias = $match[2] ?? ($last === false ? $match[1] : substr($last, 1));

        return [$alias => $match[1]];
    }
}
