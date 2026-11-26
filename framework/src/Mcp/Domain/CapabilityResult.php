<?php

declare(strict_types=1);

namespace AIC\Mcp\Domain;

/**
 * What a capability's call answered (#332): how it ended, the tool's output
 * as encoded JSON (kept as text, so a replayed call answers the same bytes),
 * or the reason it was refused; whether it ran dry, and whether it is the
 * stored answer of an earlier call with the same id.
 */
final readonly class CapabilityResult
{
    /**
     * @param CallOutcome $callOutcome How it ended.
     * @param string      $output      The tool's output, a JSON object; empty unless done.
     * @param string      $refusal     Why it was refused, did not fit, or failed; empty when done.
     * @param bool        $dryRun      Whether it ran dry, its writes rolled back.
     * @param bool        $replayed    Whether it is an earlier call's stored answer.
     */
    public function __construct(
        public CallOutcome $callOutcome,
        public string $output = '',
        public string $refusal = '',
        public bool $dryRun = false,
        public bool $replayed = false,
    ) {
    }
}
