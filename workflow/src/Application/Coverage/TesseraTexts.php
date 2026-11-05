<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Coverage;

use function is_string;
use function str_ends_with;
use function token_get_all;

use const T_COMMENT;
use const T_DOC_COMMENT;

/**
 * The app's texts as the coverage report reads them for Tessera names (#272): a PHP file or a template without its
 * comments, since a docblock that mentions a name is no use of it. A script is read as it is. The app's coverage
 * catalog never reaches it: `CodeCoverage` reads no part of `config/coverage.php` (#273).
 */
final readonly class TesseraTexts
{
    /**
     * @param array<string, string> $texts The app's files' bytes, by path.
     *
     * @return array<string, string> Them as a Tessera name counts in them, PHP and templates without their comments.
     */
    public function read(array $texts): array
    {
        $read = [];

        foreach ($texts as $path => $text) {
            $read[$path] = self::uncommented($path, $text);
        }

        return $read;
    }

    /**
     * @param string $path   A file.
     * @param string $source Its bytes.
     *
     * @return string A PHP file or a template with each comment a space; a script as it is.
     */
    private static function uncommented(string $path, string $source): string
    {
        if (!str_ends_with($path, '.php') && !str_ends_with($path, '.phtml')) {
            return $source;
        }

        $kept = '';

        foreach (token_get_all($source) as $token) {
            $kept .= match (true) {
                is_string($token) => $token,
                $token[0] === T_COMMENT, $token[0] === T_DOC_COMMENT => ' ',
                default => $token[1],
            };
        }

        return $kept;
    }
}
