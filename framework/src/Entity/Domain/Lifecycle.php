<?php

declare(strict_types=1);

namespace AIC\Entity\Domain;

/**
 * What an entity type's writes record (#309): an audit row per event when it
 * is audited, a soft delete when it names a deleted field, and a job per
 * listener. A type with none of these is not observed, and its writes record
 * nothing.
 */
final readonly class Lifecycle
{
    /**
     * @param bool         $audited   Whether each event writes a row to the audit trail (`aic_entity_audit`).
     * @param string       $deletedAt A nullable Timestamp field that `delete()` stamps instead of deleting the row;
     *                                empty for a type that deletes for good.
     * @param list<object> $listeners Its Listeners: objects, the list form elephc keeps (plan.md).
     */
    public function __construct(
        public bool $audited = false,
        public string $deletedAt = '',
        private array $listeners = [],
    ) {
    }

    /**
     * @return bool Whether a write records anything: an audit row or a listener's job.
     */
    public function isObserved(): bool
    {
        return $this->audited || $this->listeners !== [];
    }

    /**
     * @return bool Whether a delete stamps the deleted field.
     */
    public function isSoft(): bool
    {
        return $this->deletedAt !== '';
    }

    /**
     * @param EntityEvent $entityEvent An event.
     *
     * @return list<string> The jobs its listeners name, in order.
     */
    public function jobs(EntityEvent $entityEvent): array
    {
        $jobs = [];

        foreach ($this->listeners as $listener) {
            if (!$listener instanceof Listener || $listener->entityEvent !== $entityEvent) {
                continue;
            }

            $jobs[] = $listener->job;
        }

        return $jobs;
    }
}
