<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Guard;

/**
 * What the guard decided about one call (#102): allow, refuse with the rule
 * that refused and words naming who fixes it, or allow with a nudge. The
 * entry is the only place a verdict becomes an exit code.
 */
final readonly class GuardVerdict
{
    /**
     * @param string $verdict `allow`, `refuse` or `nudge`.
     * @param string $rule    The rule that decided, such as `plan-wall`; empty for a plain allow.
     * @param string $message What the agent reads; empty for a plain allow.
     */
    public function __construct(
        public string $verdict = 'allow',
        public string $rule = '',
        public string $message = '',
    ) {
    }

    /**
     * @return bool Whether the call is refused.
     */
    public function refused(): bool
    {
        return $this->verdict === 'refuse';
    }

    /**
     * @return int The hook's exit code: 2 refuses, 0 allows.
     */
    public function exitCode(): int
    {
        return $this->refused()
            ? 2
            : 0;
    }
}
