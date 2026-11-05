<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\PackFile;

/**
 * The pack `ignibyte/aic-workflow` ships (#105): its rows and their bytes, as the
 * project's vendor directory makes them.
 */
interface PackSource
{
    /**
     * @param string $tickets The project's ticket directory, where the ticket format goes.
     *
     * @return list<PackFile> Every row, in the order init applies them.
     */
    public function files(string $tickets): array;

    /**
     * @param PackFile $packFile A row.
     *
     * @return string|null The bytes it ships; null when the package lacks the file.
     */
    public function bytes(PackFile $packFile): ?string;
}
