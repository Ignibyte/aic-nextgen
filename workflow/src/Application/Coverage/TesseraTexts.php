<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Coverage;

use function array_diff_key;
use function is_string;
use function str_ends_with;
use function token_get_all;

use const T_COMMENT;
use const T_DOC_COMMENT;

/**
 * The app's texts as the coverage report reads them for Tessera names (#272): a PHP file or a template without its
 * comments, since a docblock that mentions a name is no use of it, and none of `config/coverage.php`, the app's
 * coverage catalog, which names every surface by design. A script is read as it is.
 */
final readonly class TesseraTexts
{
    /**
     * The app's coverage catalog.
     */
    private const string CATALOG = 'config/coverage.php';

    /**
     * @param array<string, string> $texts The app's files' bytes, by path.
     *
     * @return array<string, string> The ones a Tessera name counts in, PHP and templates without their comments.
     */
    public function read(array $texts): array
    {
        $read = [];

        foreach (array_diff_key($texts, [self::CATALOG => true]) as $path => $text) {
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
