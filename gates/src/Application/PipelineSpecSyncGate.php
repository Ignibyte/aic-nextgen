<?php

declare(strict_types=1);

namespace Gates\Application;

use function array_merge;
use function count;
use function fwrite;
use function sprintf;

use const PHP_EOL;
use const STDERR;
use const STDOUT;

/**
 * Gate #22 — pipeline spec / notes synchronization.
 *
 * Walks every spec-shaped markdown file under the project's pipeline +
 * skill + knowledge-graph trees and enforces the compact-spec contract:
 *
 *   1. Frontmatter parses and declares the required fields for its kind.
 *   2. Body (frontmatter excluded) is at or below the per-kind line cap
 *      — 80 lines for pipeline specs, 100 lines for skill specs and
 *      knowledge-graph leaf nodes.
 *   3. Every internal markdown link `[text](relative-path)` resolves to
 *      a real file on disk.
 *
 * The gate is migration-aware: legacy monolithic `WORK-<title>.md` docs
 * (no spec/notes split) are ignored. The gate only inspects files whose
 * filename ends in `.spec.md`, `.notes.md`, or `.runbook.md`, plus every
 * leaf node under `docs/planning/specs/`.
 *
 * Designed for in-process testing via `SandboxFixture` and direct
 * invocation from `bin/gate-pipeline-spec-sync.php`.
 *
 * @api
 */
final readonly class PipelineSpecSyncGate
{
    /**
     * @param PipelineSpecFileFinder       $pipelineSpecFileFinder       Tree walker collecting spec-shaped files.
     * @param PipelineSpecPerFileValidator $pipelineSpecPerFileValidator Per-file orchestrator (frontmatter + cap + links + cross-check).
     * @param PipelineSpecPlanSkillAuditor $pipelineSpecPlanSkillAuditor R13 plan-skill template-instantiation audit.
     */
    public function __construct(
        private PipelineSpecFileFinder $pipelineSpecFileFinder = new PipelineSpecFileFinder(),
        private PipelineSpecPerFileValidator $pipelineSpecPerFileValidator = new PipelineSpecPerFileValidator(),
        private PipelineSpecPlanSkillAuditor $pipelineSpecPlanSkillAuditor = new PipelineSpecPlanSkillAuditor(),
    ) {
    }

    /**
     * Run the gate against `$root` and return the CLI exit code.
     *
     * @param string   $root   Repo-root absolute path.
     * @param resource $stdout Stream success messages are written to.
     * @param resource $stderr Stream failure messages are written to.
     */
    public function run(string $root, mixed $stdout = STDOUT, mixed $stderr = STDERR): int
    {
        $files = $this->pipelineSpecFileFinder->collect($root);
        $errors = $this->collectErrors($root, $files);

        if ($errors !== []) {
            $this->reportFailures($errors, $stderr);

            return 1;
        }

        fwrite($stdout, sprintf(
            'gate:22 — pipeline spec/notes sync clean: %d spec-shaped file(s) scanned.%s',
            count($files),
            PHP_EOL,
        ));

        return 0;
    }

    /**
     * Aggregate every per-file finding plus the cross-cutting plan-skill audit.
     *
     * @param string       $root  Repo-root absolute path.
     * @param list<string> $files Absolute paths of spec-shaped files to inspect.
     *
     * @return list<string>
     */
    private function collectErrors(string $root, array $files): array
    {
        $errors = [];

        foreach ($files as $file) {
            $errors = array_merge($errors, $this->pipelineSpecPerFileValidator->validate($root, $file));
        }

        return array_merge(
            $errors,
            $this->pipelineSpecPlanSkillAuditor->validate($root),
        );
    }

    /**
     * Emit the failure report (per-finding diagnostic lines) to `$stderr`.
     *
     * @param list<string> $errors All accumulated error messages.
     * @param resource     $stderr Stream to write to.
     */
    private function reportFailures(array $errors, mixed $stderr): void
    {
        fwrite($stderr, sprintf(
            'gate:22 — pipeline spec/notes sync violation (%d finding(s)).%s%s',
            count($errors),
            PHP_EOL,
            PHP_EOL,
        ));

        foreach ($errors as $error) {
            fwrite($stderr, sprintf('  - %s%s', $error, PHP_EOL));
        }

        $resolution = 'Resolution: fix the spec, fix the link, or update the frontmatter '
            . '— see CONSTITUTION.md §4 (the pipeline doc shape).';

        fwrite($stderr, PHP_EOL . $resolution . PHP_EOL);
    }
}
