<?php

declare(strict_types=1);

namespace App\Ledger\Service;

use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\AuditTrail;
use AIC\Entity\Domain\EntityType;

/**
 * An audited ledger row's history as lines (#309, #308): each event, who
 * wrote it and the values written, oldest first, never the time.
 */
final readonly class LedgerHistory
{
    /**
     * @param AuditTrail $auditTrail The audit trail.
     */
    public function __construct(
        private AuditTrail $auditTrail,
    ) {
    }

    /**
     * @param EntityType $entityType An audited type.
     * @param int        $key        One of its rows' keys.
     *
     * @throws QueryException When the trail cannot be read.
     *
     * @return list<string> `<event> by <actor>: <changes>`, oldest first.
     */
    public function lines(EntityType $entityType, int $key): array
    {
        $lines = [];

        foreach ($this->auditTrail->history($entityType, $key) as $row) {
            $lines[] = $row->string('event') . ' by ' . $row->string('actor') . ': ' . $row->string('changes');
        }

        return $lines;
    }
}
