<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Probe;

/**
 * One row a phase call's report must hold (#119): its check, its status, a
 * text its reason contains, quoted from the engine's own row, and a text it
 * must not.
 */
final readonly class Expect
{
    /**
     * @param string $check    The row's check, such as `declaration` or `gate:10`.
     * @param string $status   Its status, such as `blocked` or `passed`.
     * @param string $contains A text its reason holds; empty for any.
     * @param string $lacks    A text its reason must not hold; empty for none.
     */
    public function __construct(
        public string $check,
        public string $status,
        public string $contains = '',
        public string $lacks = '',
    ) {
    }

    /**
     * @return string The expectation, for its line.
     */
    public function text(): string
    {
        return $this->check . ' ' . $this->status . ($this->contains === '' ? '' : ' "' . $this->contains . '"')
            . ($this->lacks === '' ? '' : ' without "' . $this->lacks . '"');
    }
}
