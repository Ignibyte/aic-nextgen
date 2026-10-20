<?php

declare(strict_types=1);

namespace AIC\Notification\Contracts;

use RuntimeException;

/**
 * One way a notification reaches its user (#48): mail, the database.
 */
interface Channel
{
    /**
     * @param Notification $notification What to deliver.
     *
     * @throws RuntimeException When it cannot be delivered now; the queue retries.
     */
    public function deliver(Notification $notification): void;
}
