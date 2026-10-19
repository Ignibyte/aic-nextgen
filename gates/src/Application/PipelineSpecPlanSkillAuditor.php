<?php

declare(strict_types=1);

namespace Gates\Application;

use function explode;
use function file_exists;
use function sprintf;
use function str_contains;

/**
 * Audits `.claude/commands/pipeline/plan.md` for the
 * template-instantiation contract (gate:22 R13).
 *
 * Two predicates are enforced against the plan-skill body:
 *
 *   1. POSITIVE — body MUST contain the literal substring `_templates/`,
 *      meaning the skill points operators at the canonical pipeline-doc
 *      templates rather than inline-generating the body.
 *
 *   2. NEGATIVE — body MUST NOT contain inline frontmatter blocks
 *      (extra `^---$` lines after the file's own opening + closing
 *      frontmatter delimiters). An inline template block would mean the
 *      skill is silently re-introducing the inline-generation it is
 *      supposed to forbid.
 *
 * Failure surfaces a `plan-skill-template-instantiation-missing:` or
 * `plan-skill-inline-template-detected:` diagnostic, identified by the
 * project-relative path of `plan.md`. A missing `plan.md` is treated as
 * vacuously satisfied — gate:19 (doc-freshness) is responsible for
 * catching deleted skill files via dead-link enforcement; R13 is scoped
 * to the contract `plan.md` MUST follow when present.
 *
 * Constructor takes no parameters; callers inject via `PipelineSpecSyncGate`
 * mirroring the pattern set by `PipelineSpecFrontmatterParser` and
 * `PipelineSpecLinkResolver`.
 *
 * @api
 */
final readonly class PipelineSpecPlanSkillAuditor
{
    private const string PLAN_SKILL_PATH = '.claude/commands/pipeline/plan.md';

    /**
     * @param QuietFileReader $quietFileReader Reads a file, null on failure.
     */
    public function __construct(
        private QuietFileReader $quietFileReader = new QuietFileReader(),
    ) {
    }

    /**
     * Validate the plan-skill body satisfies the template-instantiation contract.
     *
     * @param string $root Repo-root absolute path.
     *
     * @return list<string>
     */
    public function validate(string $root): array
    {
        $absolute = $root . '/' . self::PLAN_SKILL_PATH;

        return file_exists($absolute)
            ? $this->validateBody($absolute)
            : [];
    }

    /**
     * Read `$absolute` and run R13's predicates against it. Returns a
     * `plan-skill-unreadable` diagnostic when the file cannot be read.
     *
     * @param string $absolute Absolute path to the plan-skill file (already confirmed to exist).
     *
     * @return list<string>
     */
    private function validateBody(string $absolute): array
    {
        $body = $this->quietFileReader->read($absolute);

        return $body === null
            ? [sprintf('%s — plan-skill-unreadable', self::PLAN_SKILL_PATH)]
            : $this->checkPredicates($body);
    }

    /**
     * Run R13's positive + negative predicates against the plan-skill body.
     *
     * @param string $body Plan-skill file contents.
     *
     * @return list<string>
     */
    private function checkPredicates(string $body): array
    {
        $errors = [];

        if (!str_contains($body, '_templates/')) {
            $errors[] = sprintf('%s — plan-skill-template-instantiation-missing', self::PLAN_SKILL_PATH);
        }

        if ($this->countExtraDelimiters($body) > 0) {
            $errors[] = sprintf('%s — plan-skill-inline-template-detected', self::PLAN_SKILL_PATH);
        }

        return $errors;
    }

    /**
     * Count `^---$` lines beyond the file's own opening + closing
     * frontmatter delimiters.
     *
     * The plan-skill body has its own frontmatter (opening `---` + closing
     * `---`). Any THIRD or later `^---$` line is an inline-template
     * artefact and triggers R13's negative predicate.
     *
     * @param string $body Plan-skill file contents.
     */
    private function countExtraDelimiters(string $body): int
    {
        $delimiterCount = 0;

        foreach (explode("\n", $body) as $line) {
            if ($line !== '---') {
                continue;
            }

            $delimiterCount += 1;
        }

        return $delimiterCount > 2
            ? $delimiterCount - 2
            : 0;
    }
}
