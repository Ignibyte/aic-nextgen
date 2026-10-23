<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure\Guard;

use AIC\Workflow\Domain\RegexCaptures;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_key_exists;
use function array_last;
use function array_slice;
use function array_unique;
use function array_values;
use function count;
use function explode;
use function str_replace;
use function str_starts_with;
use function substr;
use function trim;

/** Read every destination before Codex can apply any part of a patch. */
final readonly class CodexPatch
{
    use RegexCaptures;

    /**
     * @param string $patch The native apply_patch command.
     *
     * @throws WorkflowRefusal When a patch cannot be judged completely.
     *
     * @return list<string> Every original and moved destination.
     */
    public function paths(string $patch): array
    {
        $paths = [];
        $kind = '';

        foreach ($this->lines($patch) as $line) {
            $file = $this->file($line);

            if ($file !== null) {
                $kind = $file['kind'];
                $paths[] = $file['path'];

                continue;
            }

            $move = $this->move($line, $kind);

            if ($move === null) {
                continue;
            }

            $paths[] = $move;
        }

        return $this->destinations($paths);
    }

    /**
     * @param list<string> $paths All parsed destinations.
     *
     * @throws WorkflowRefusal When no destination was found.
     *
     * @return non-empty-list<string> Every distinct destination.
     */
    private function destinations(array $paths): array
    {
        return $paths === []
            ? throw new WorkflowRefusal('The patch names no file.')
            : array_values(array_unique($paths));
    }

    /**
     * @param string $patch The native patch.
     *
     * @throws WorkflowRefusal When framing is absent or ambiguous.
     *
     * @return list<string> The framed body.
     */
    private function lines(string $patch): array
    {
        $lines = explode("\n", str_replace("\r\n", "\n", trim($patch)));

        if (count($lines) < 2 || $lines[0] !== '*** Begin Patch' || array_last($lines) !== '*** End Patch') {
            throw new WorkflowRefusal('Use one native patch with complete Begin Patch and End Patch framing.');
        }

        return array_slice($lines, 1, -1);
    }

    /**
     * @param string $line One patch line.
     *
     * @throws WorkflowRefusal When a path is ambiguous.
     *
     * @return array{kind:string,path:string}|null Its file header, or null.
     */
    private function file(string $line): ?array
    {
        $match = self::regexMatch('/^\\*\\*\\* (Add|Update|Delete) File: (.+)$/D', $line);

        return array_key_exists(1, $match) && array_key_exists(2, $match)
            ? ['kind' => $match[1], 'path' => $this->path($match[2])]
            : null;
    }

    /**
     * @param string $line One patch line.
     * @param string $kind Its current file operation.
     *
     * @throws WorkflowRefusal When a move or header cannot be judged.
     *
     * @return string|null The move destination, or null.
     */
    private function move(string $line, string $kind): ?string
    {
        if (str_starts_with($line, '*** Move to: ')) {
            return $kind === 'Update'
                ? $this->path(substr($line, 13))
                : throw new WorkflowRefusal('A move requires an Update File header.');
        }

        if (str_starts_with($line, '***') && $line !== '*** End of File') {
            throw new WorkflowRefusal('The patch contains an unsupported header.');
        }

        return null;
    }

    /**
     * @param string $path The exact native path.
     *
     * @throws WorkflowRefusal When whitespace makes its interpretation ambiguous.
     *
     * @return string The unchanged path.
     */
    private function path(string $path): string
    {
        return $path !== '' && $path === trim($path)
            ? $path
            : throw new WorkflowRefusal('The patch path is empty or has ambiguous outer whitespace.');
    }
}
