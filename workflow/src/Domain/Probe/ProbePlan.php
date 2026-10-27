<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Probe;

/**
 * The steps most scenarios share (#119): the run from ticket 1 through plan
 * to code, with the scenario's `spec.md.fixture` and plan's lookups; the
 * change the shared ticket asks for, the status body's database description (a
 * service, so no browser look is due, #174); and
 * code's first green exit, which asks for the seeker, with its ledger filed
 * (the seeker reads only a tree that passed the gates).
 */
final readonly class ProbePlan
{
    /**
     * @return list<ProbeStep> Begin, write the spec, look, leave plan.
     */
    public function toCode(): array
    {
        return [
            new RunStep('blocked', ticket: 1),
            new WriteStep('var/workflow/spec-1.md', 'spec.md.fixture'),
            new LookStep(),
            new RunStep('advanced', [new Expect('grounding', 'passed')]),
        ];
    }

    /**
     * @return list<ProbeStep> Code's exit on a green tree, inspection due, then the seeker's ledger filed.
     */
    public function inspected(): array
    {
        return [new RunStep('inspection-due', [new Expect('gate', 'passed'), new Expect('seeker', 'due')]), new SeekerStep()];
    }

    /**
     * @return ReplaceStep The ticket's change: the status body's database description.
     */
    public function label(): ReplaceStep
    {
        return new ReplaceStep(
            'modules/site/src/Service/StatusBody.php',
            "The database server's version, or `unavailable`.",
            "The database server's version; `unavailable` when it does not answer.",
        );
    }
}
