<?php

declare(strict_types=1);

namespace Gates\Application;

use function array_merge;
use function mb_strlen;
use function mb_substr;
use function realpath;
use function sprintf;

/**
 * Per-file orchestrator for gate:22.
 *
 * For each spec-shaped markdown file the gate finds, this collaborator:
 *   1. Reads the file body and splits frontmatter from body.
 *   2. Classifies the file's kind via `PipelineSpecKindValidator`.
 *   3. Fans out to the per-kind frontmatter + line-cap checks.
 *   4. Runs the link resolver over the body.
 *
 * Extracted from `PipelineSpecSyncGate` so the outer gate stays under
 * PHPMD's `ExcessiveClassLength` ceiling.
 *
 * Constructor takes no parameters; callers inject via `PipelineSpecSyncGate`
 * mirroring the pattern set by the other gate:22 collaborators.
 *
 * @api
 */
final readonly class PipelineSpecPerFileValidator
{
    /**
     * @param PipelineSpecFrontmatterParser $pipelineSpecFrontmatterParser YAML frontmatter splitter + parser.
     * @param PipelineSpecLinkResolver      $pipelineSpecLinkResolver      Internal markdown link validator.
     * @param PipelineSpecKindValidator     $pipelineSpecKindValidator     Per-kind classifier + frontmatter/line-cap checks.
     * @param QuietFileReader               $quietFileReader               Reads a file, null on failure.
     */
    public function __construct(
        private PipelineSpecFrontmatterParser $pipelineSpecFrontmatterParser = new PipelineSpecFrontmatterParser(),
        private PipelineSpecLinkResolver $pipelineSpecLinkResolver = new PipelineSpecLinkResolver(),
        private PipelineSpecKindValidator $pipelineSpecKindValidator = new PipelineSpecKindValidator(),
        private QuietFileReader $quietFileReader = new QuietFileReader(),
    ) {
    }

    /**
     * Validate one spec-shaped file. Returns a list of diagnostic strings.
     *
     * @param string $root Repo-root absolute path.
     * @param string $file Absolute path to the file under inspection.
     *
     * @return list<string>
     */
    public function validate(string $root, string $file): array
    {
        $relative = $this->relativePath($root, $file);
        $contents = $this->quietFileReader->read($file);

        if ($contents === null) {
            return [sprintf('%s — cannot read', $relative)];
        }

        $parsed = $this->pipelineSpecFrontmatterParser->split($contents);

        if ($parsed === null) {
            return [sprintf('%s — frontmatter-invalid (no leading `---` block)', $relative)];
        }

        $kind = $this->pipelineSpecKindValidator->classify($file);

        if ($kind === 'template') {
            return [];
        }

        return array_merge(
            $this->pipelineSpecKindValidator->validateFrontmatterFields($relative, $parsed['frontmatter'], $kind),
            $this->pipelineSpecKindValidator->validateLineCap($relative, $parsed['body'], $kind),
            $this->pipelineSpecLinkResolver->validate($relative, $file, $parsed['body']),
        );
    }

    /**
     * Convert an absolute path to a project-relative path for diagnostic output.
     *
     * @param string $root     Repo-root absolute path.
     * @param string $absolute Absolute path to relativise against the root.
     */
    private function relativePath(string $root, string $absolute): string
    {
        $rootReal = (string) realpath($root);

        return mb_substr((string) realpath($absolute), mb_strlen($rootReal) + 1);
    }
}
