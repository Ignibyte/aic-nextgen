<?php

declare(strict_types=1);

namespace Gates\Application;

use function array_filter;
use function array_values;
use function basename;
use function mb_strtolower;
use function str_contains;
use function str_ends_with;

/**
 * Walks a project tree and collects every spec-shaped markdown file
 * gate:22 should inspect.
 *
 * Spec-shaped means any of:
 *   - filename ending in `.spec.md` / `.notes.md` / `.runbook.md`, OR
 *   - any `.md` file under `.claude/commands/pipeline/` (phase-skill specs
 *     whose filename is the bare slash-command form `<phase>.md`).
 *
 * The walker skips standard vendor, cache, and storage directories so the
 * gate completes in <1s on a clean working tree.
 *
 * Constructor takes no parameters; callers inject via `PipelineSpecSyncGate`
 * mirroring the pattern set by the other gate:22 collaborators.
 */
final readonly class PipelineSpecFileFinder
{
    private const array SKIP_DIRS = [
        'vendor',
        'node_modules',
        '.git',
        '.ddev',
        '.native-gate',
        '.playwright-mcp',
        '.psalm-cache',
        '.rector-cache',
        'tools',
        'storage',
    ];

    /**
     * @param MarkdownTreeWalker $markdownTreeWalker Lists the markdown files under the tree.
     */
    public function __construct(
        private MarkdownTreeWalker $markdownTreeWalker = new MarkdownTreeWalker(),
    ) {
    }

    /**
     * Collect every spec-shaped markdown file under `$root`, sorted lexicographically.
     *
     * @param string $root Repo-root absolute path.
     *
     * @return list<string>
     */
    public function collect(string $root): array
    {
        return array_values(array_filter(
            $this->markdownTreeWalker->markdownFiles($root, self::SKIP_DIRS),
            $this->isSpecShapedFile(...),
        ));
    }

    /**
     * Decide whether a markdown file is spec-shaped.
     *
     * @param string $path Absolute path of a `*.md` file.
     *
     * @return bool True for a spec, notes or runbook file, or a phase command.
     */
    private function isSpecShapedFile(string $path): bool
    {
        $name = mb_strtolower(basename($path));

        return str_ends_with($name, '.spec.md')
            || str_ends_with($name, '.notes.md')
            || str_ends_with($name, '.runbook.md')
            || str_contains(mb_strtolower($path), '/.claude/commands/pipeline/');
    }
}
