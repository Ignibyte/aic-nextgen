<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Probe;

use Override;

/**
 * One call of the installed guard in the app (#119): a hook payload, with
 * `{root}` standing for the app's directory, into
 * `vendor/aic/workflow/bin/aic-guard.sh <verb>`. It holds when the guard
 * exits as expected and its answer holds the text.
 */
final readonly class GuardStep implements ProbeStep
{
    /**
     * @param string $what     What the call tries, for its line.
     * @param string $verb     `edit`, `shell`, `record`, `stop` or `returned`.
     * @param string $payload  The hook's JSON payload.
     * @param int    $exit     The exit it must end with: 0 allowed, 2 refused.
     * @param string $contains A text its answer holds; empty for any.
     */
    public function __construct(
        public string $what,
        public string $verb,
        public string $payload,
        public int $exit,
        public string $contains = '',
    ) {
    }

    /**
     * @return string What the step does.
     */
    #[Override]
    public function label(): string
    {
        return 'guard ' . $this->verb . ': ' . $this->what;
    }

    /**
     * @return int One.
     */
    #[Override]
    public function expectations(): int
    {
        return 1;
    }
}
