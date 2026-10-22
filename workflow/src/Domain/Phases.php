<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * The phases by name and in order (#100), read without an enum's static
 * `from()`, which AIC's static-access rule refuses.
 */
final readonly class Phases
{
    /**
     * @return list<Phase> All four, in order.
     */
    public function all(): array
    {
        return [Phase::Plan, Phase::Code, Phase::Check, Phase::Complete];
    }

    /**
     * @param string $name A phase's name.
     *
     * @return Phase|null The phase of that name; none for any other.
     */
    public function named(string $name): ?Phase
    {
        foreach ($this->all() as $phase) {
            if ($phase->value === $name) {
                return $phase;
            }
        }

        return null;
    }
}
