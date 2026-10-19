<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * The result of probing one runtime class natively (gate:21).
 */
final readonly class NativeProbeResult
{
    /**
     * @param string            $root   FQCN of the probed runtime class.
     * @param NativeProbeStatus $status How far the probe got.
     * @param string            $detail The first error line, or the differing outputs; empty when OK.
     */
    public function __construct(
        public string $root,
        public NativeProbeStatus $status,
        public string $detail = '',
    ) {
    }

    /**
     * Whether the probe passed: compiled, linked, ran and matched PHP.
     *
     * @return bool True when the status is OK.
     */
    public function passed(): bool
    {
        return $this->status === NativeProbeStatus::Ok;
    }
}
