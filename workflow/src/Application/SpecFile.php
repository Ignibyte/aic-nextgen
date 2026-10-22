<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\DeclaredEntry;

use function file_get_contents;
use function is_file;
use function is_link;
use function realpath;
use function str_starts_with;
use function trim;

/**
 * The run's spec on disk (#100, #101): a regular file inside the project, at
 * the run's spec path, read only when it is one and has text in it, and read
 * as a contract.
 */
final readonly class SpecFile
{
    /**
     * @param string $root The project's root.
     */
    public function __construct(
        private string $root,
    ) {
    }

    /**
     * @param string $specPath The spec's path, relative to the project.
     *
     * @return string|null Its text; null when it is no regular file inside the project, a symlink, or empty.
     */
    public function text(string $specPath): ?string
    {
        $path = $this->root . '/' . $specPath;
        $real = realpath($path);
        $root = realpath($this->root);

        if (
            !is_file($path)
            || is_link($path)
            || $real === false
            || $root === false
            || !str_starts_with($real, $root . '/')
        ) {
            return null;
        }

        $text = (string) file_get_contents($path);

        return trim($text) === ''
            ? null
            : $text;
    }

    /**
     * @param string $text A spec's text.
     *
     * @return SpecContract It as a contract.
     */
    public function contract(string $text): SpecContract
    {
        return new SpecContract(new MarkdownSections($text), $text);
    }

    /**
     * @param string $specPath The spec's path.
     *
     * @return list<DeclaredEntry> The entry points it declares; none when it is not there.
     */
    public function entries(string $specPath): array
    {
        $text = $this->text($specPath);

        return $text === null
            ? []
            : $this->contract($text)->entries();
    }
}
