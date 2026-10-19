<?php

declare(strict_types=1);

namespace Gates\Application;

use function array_diff;
use function array_key_exists;
use function array_keys;
use function array_values;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_int;
use function mb_rtrim;
use function mb_strtolower;
use function mb_substr_count;
use function sprintf;
use function str_contains;
use function str_ends_with;

/**
 * Per-kind validator for spec-shaped markdown files (gate:22).
 *
 * Encapsulates three concerns extracted from `PipelineSpecSyncGate` so the
 * outer gate stays under PHPMD's `ExcessiveClassLength` ceiling:
 *
 *   1. `classify()` — assign a kind label (`pipeline-spec`, `pipeline-notes`,
 *      `skill-spec`, `skill-runbook`, `knowledge-graph`, `template`,
 *      `unknown`) based on a file's path and suffix.
 *
 *   2. `validateFrontmatterFields()` — ensure the parsed frontmatter
 *      key/value map declares every required field for the file's kind.
 *
 *   3. `validateLineCap()` — ensure the markdown body (frontmatter excluded)
 *      is at or below the per-kind line cap (80 for pipeline specs, 100 for
 *      skill specs and knowledge-graph leaf nodes).
 *
 * Constructor takes no parameters; callers inject via `PipelineSpecSyncGate`
 * mirroring the pattern set by `PipelineSpecFrontmatterParser` and
 * `PipelineSpecLinkResolver`.
 *
 * @api
 */
final readonly class PipelineSpecKindValidator
{
    private const int PIPELINE_SPEC_LINE_CAP = 80;

    private const int SKILL_SPEC_LINE_CAP = 100;

    private const int KNOWLEDGE_GRAPH_LINE_CAP = 100;

    private const array PIPELINE_SPEC_REQUIRED = [
        'pipeline_id',
        'ticket',
        'status',
        'title',
        'notes',
    ];

    private const array PIPELINE_NOTES_REQUIRED = [
        'spec',
        'loaded_on',
        'purpose',
    ];

    private const array SKILL_SPEC_REQUIRED = [
        'phase',
        'runbook',
        'title',
        'purpose',
    ];

    private const array SKILL_RUNBOOK_REQUIRED = [
        'spec',
        'loaded_on',
        'purpose',
    ];

    private const array KNOWLEDGE_GRAPH_REQUIRED = [
        'node',
        'loaded_when',
    ];

    private const array AUTO_APPROVE_VALID_SCOPES = [
        'standard',
        'through-merge',
        'dry-run',
    ];

    /**
     * Classify a spec-shaped file by its location and suffix.
     *
     * Returns one of: `pipeline-spec`, `pipeline-notes`, `skill-spec`,
     * `skill-runbook`, `knowledge-graph`, `template`, `unknown`.
     *
     * Phase skills under `.claude/commands/pipeline/`: `<phase>.md` is a
     * `skill-spec`; `<phase>.runbook.md` is a `skill-runbook`. Other files
     * under `.claude/commands/` (non-pipeline slash commands, e.g. `/work`,
     * `/commit`) are `unknown` and not enforced by gate:22.
     *
     * @param string $file Absolute path to the file under inspection.
     */
    public function classify(string $file): string
    {
        $name = mb_strtolower($file);
        $isSpec = str_ends_with($name, '.spec.md');
        $isRunbook = str_ends_with($name, '.runbook.md');

        return match (true) {
            str_contains($name, '/_templates/') => 'template',
            str_contains($name, '/specs/') && $isSpec => 'knowledge-graph',
            str_contains($name, '/.claude/commands/pipeline/') => $isRunbook ? 'skill-runbook' : 'skill-spec',
            str_contains($name, '/pipeline/') => $isSpec ? 'pipeline-spec' : 'pipeline-notes',
            default => 'unknown',
        };
    }

    /**
     * Validate required frontmatter fields are present for the given kind.
     *
     * @param string                                            $relative    Project-relative path of the source file (used in diagnostics).
     * @param array<string, scalar|list<array<string, string>>> $frontmatter Parsed key/value map.
     * @param string                                            $kind        Classification result from `classify()`.
     *
     * @return list<string>
     */
    public function validateFrontmatterFields(string $relative, array $frontmatter, string $kind): array
    {
        $required = match ($kind) {
            'pipeline-spec' => self::PIPELINE_SPEC_REQUIRED,
            'pipeline-notes' => self::PIPELINE_NOTES_REQUIRED,
            'skill-spec' => self::SKILL_SPEC_REQUIRED,
            'skill-runbook' => self::SKILL_RUNBOOK_REQUIRED,
            // `knowledge-leaf` is accepted as an alias of `knowledge-graph`.
            'knowledge-graph', 'knowledge-leaf' => self::KNOWLEDGE_GRAPH_REQUIRED,
            default => [],
        };

        $missing = array_values(array_diff($required, array_keys($frontmatter)));
        $errors = [];

        if ($missing !== []) {
            $errors[] = sprintf('%s — frontmatter-missing-fields: %s', $relative, implode(', ', $missing));
        }

        $errors = [...$errors, ...$this->validateLoadedOn($relative, $frontmatter, $kind)];

        if ($kind === 'pipeline-spec' && array_key_exists('auto_approve', $frontmatter)) {
            return [...$errors, ...$this->validateAutoApproveFields($relative, $frontmatter)];
        }

        return $errors;
    }

    /**
     * Validate the body line count is at or below the per-kind cap.
     *
     * @param string $relative Project-relative path of the source file (used in diagnostics).
     * @param string $body     Markdown body (frontmatter excluded).
     * @param string $kind     Classification result from `classify()`.
     *
     * @return list<string>
     */
    public function validateLineCap(string $relative, string $body, string $kind): array
    {
        $cap = match ($kind) {
            'pipeline-spec' => self::PIPELINE_SPEC_LINE_CAP,
            'skill-spec' => self::SKILL_SPEC_LINE_CAP,
            'knowledge-graph', 'knowledge-leaf' => self::KNOWLEDGE_GRAPH_LINE_CAP,
            default => null,
        };

        if ($cap === null) {
            return [];
        }

        $count = mb_substr_count(mb_rtrim($body, "\n"), "\n") + 1;

        if ($count <= $cap) {
            return [];
        }

        return [sprintf('%s — line-cap-exceeded: %d > %d (kind=%s)', $relative, $count, $cap, $kind)];
    }

    /**
     * @param string                                            $relative    Source file path (diagnostics).
     * @param array<string, scalar|list<array<string, string>>> $frontmatter Parsed key/value map.
     * @param string                                            $kind        Classification result from `classify()`.
     *
     * @return list<string>
     */
    private function validateLoadedOn(string $relative, array $frontmatter, string $kind): array
    {
        if ($kind !== 'pipeline-notes' && $kind !== 'skill-runbook') {
            return [];
        }

        if (!array_key_exists('loaded_on', $frontmatter)) {
            return [];
        }

        if ($frontmatter['loaded_on'] === 'demand') {
            return [];
        }

        return [sprintf('%s — invalid-loaded_on: must be "demand"', $relative)];
    }

    /**
     * Validate the optional auto-approve frontmatter fields (the boolean, its
     * scope, threshold and recorded violations) when a spec declares them.
     *
     * @param string                                            $relative    Source file path (diagnostics).
     * @param array<string, scalar|list<array<string, string>>> $frontmatter Parsed key/value map.
     *
     * @return list<string>
     */
    private function validateAutoApproveFields(string $relative, array $frontmatter): array
    {
        return [
            ...$this->validateAutoApproveBoolean($relative, $frontmatter),
            ...$this->validateAutoApproveScope($relative, $frontmatter),
            ...$this->validateAutoApproveThreshold($relative, $frontmatter),
            ...$this->validateAutoApproveViolations($relative, $frontmatter),
        ];
    }

    /**
     * @param string                                            $relative    Source file path (diagnostics).
     * @param array<string, scalar|list<array<string, string>>> $frontmatter Parsed key/value map.
     *
     * @return list<string>
     */
    private function validateAutoApproveBoolean(string $relative, array $frontmatter): array
    {
        if (is_bool($frontmatter['auto_approve'] ?? null)) {
            return [];
        }

        return [sprintf('%s — invalid-auto_approve: must be boolean', $relative)];
    }

    /**
     * @param string                                            $relative    Source file path (diagnostics).
     * @param array<string, scalar|list<array<string, string>>> $frontmatter Parsed key/value map.
     *
     * @return list<string>
     */
    private function validateAutoApproveScope(string $relative, array $frontmatter): array
    {
        if (!array_key_exists('auto_approve_scope', $frontmatter)) {
            return [];
        }

        if (in_array($frontmatter['auto_approve_scope'], self::AUTO_APPROVE_VALID_SCOPES, true)) {
            return [];
        }

        return [sprintf(
            '%s — invalid-auto_approve_scope: must be standard|through-merge|dry-run (resume/halt are control commands)',
            $relative,
        )];
    }

    /**
     * @param string                                            $relative    Source file path (diagnostics).
     * @param array<string, scalar|list<array<string, string>>> $frontmatter Parsed key/value map.
     *
     * @return list<string>
     */
    private function validateAutoApproveThreshold(string $relative, array $frontmatter): array
    {
        if (!array_key_exists('auto_approve_judgment_threshold', $frontmatter)) {
            return [];
        }

        $threshold = $frontmatter['auto_approve_judgment_threshold'];

        if (is_int($threshold) && $threshold >= 0 && $threshold <= 100) {
            return [];
        }

        return [sprintf('%s — invalid-auto_approve_judgment_threshold: must be int 0-100', $relative)];
    }

    /**
     * @param string                                            $relative    Source file path (diagnostics).
     * @param array<string, scalar|list<array<string, string>>> $frontmatter Parsed key/value map.
     *
     * @return list<string>
     */
    private function validateAutoApproveViolations(string $relative, array $frontmatter): array
    {
        if (!array_key_exists('auto_approve_violations', $frontmatter)) {
            return [];
        }

        $violations = $frontmatter['auto_approve_violations'];

        if (!is_array($violations)) {
            return [sprintf('%s — invalid-auto_approve_violations: must be a list', $relative)];
        }

        foreach ($violations as $violation) {
            if (!$this->isValidViolationRow($violation)) {
                return [sprintf('%s — invalid-auto_approve_violation-row: each row needs rule + fired_at', $relative)];
            }
        }

        return [];
    }

    /**
     * Whether `$violation` is a row carrying both `rule` and `fired_at`
     * keys — the minimum shape the auto-approve contract requires of
     * each entry under `auto_approve_violations`.
     *
     * @param bool|int|string|array<string, string> $violation Candidate row from `auto_approve_violations`.
     */
    private function isValidViolationRow(bool|int|string|array $violation): bool
    {
        return is_array($violation)
            && array_key_exists('rule', $violation)
            && array_key_exists('fired_at', $violation);
    }
}
