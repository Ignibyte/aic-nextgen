<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Guard;

/**
 * One line of the guard's ledger, `var/workflow/guard-calls.jsonl` (#102,
 * Droost's guard-calls row trimmed): when, which run and phase, which hook
 * verb and tool, and what the guard decided by which rule.
 */
final readonly class GuardRow
{
    /**
     * @param string $time     When, UTC.
     * @param string $run      The open run's id; empty when none is open.
     * @param string $phase    Its current phase; empty when none.
     * @param string $hook     The verb: `edit`, `shell`, `record` or `stop`.
     * @param string $tool     The tool; empty for Stop.
     * @param string $verdict  `allow`, `refuse` or `nudge`.
     * @param string $rule     The rule; empty for a plain allow.
     * @param string $agent    The session.
     * @param string $subagent A returned subagent's type, on a `returned` row (#103); empty otherwise.
     * @param string $digest   The digest of the ledger it returned (#103); empty otherwise.
     */
    public function __construct(
        public string $time,
        public string $run,
        public string $phase,
        public string $hook,
        public string $tool,
        public string $verdict,
        public string $rule,
        public string $agent,
        public string $subagent = '',
        public string $digest = '',
    ) {
    }

    /**
     * @return array{
     *     agent: string,
     *     at: string,
     *     digest?: string,
     *     hook: string,
     *     phase: string,
     *     rule: string,
     *     run: string,
     *     subagent?: string,
     *     tool: string,
     *     verdict: string
     * } Its JSON form; a `returned` row also names the subagent and its digest.
     */
    public function toArray(): array
    {
        $row = [
            'agent' => $this->agent,
            'at' => $this->time,
            'hook' => $this->hook,
            'phase' => $this->phase,
            'rule' => $this->rule,
            'run' => $this->run,
            'tool' => $this->tool,
            'verdict' => $this->verdict,
        ];

        return $this->subagent === ''
            ? $row
            : [...$row, 'digest' => $this->digest, 'subagent' => $this->subagent];
    }
}
