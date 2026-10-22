<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * What a run froze when planning ended (#101): the spec's text and hash, the
 * time, and the `gate:N` names the project had then.
 */
final readonly class FrozenSpec
{
    /**
     * @param string       $text     The spec, whole.
     * @param string       $hash     Its xxh128.
     * @param string       $frozenAt When, UTC.
     * @param list<string> $gates    The gate names, in run order.
     */
    public function __construct(
        public string $text,
        public string $hash,
        public string $frozenAt,
        public array $gates,
    ) {
    }
}
