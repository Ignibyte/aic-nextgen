<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

use function array_map;

/**
 * What `workflow:init` did (#105): its lines in order, and the lock they
 * leave, so each step hands the next the lock as it stands.
 */
final readonly class InstallReport
{
    /**
     * @param PackLock          $packLock     The lock as the steps so far leave it.
     * @param list<InstallLine> $installLines The lines so far, in order.
     */
    public function __construct(
        public PackLock $packLock,
        public array $installLines = [],
    ) {
    }

    /**
     * @param InstallLine $installLine The next line.
     *
     * @return self The report with it.
     */
    public function with(InstallLine $installLine): self
    {
        return new self($this->packLock, [...$this->installLines, $installLine]);
    }

    /**
     * @param PackLock $packLock The lock as the last step left it.
     *
     * @return self The report with it.
     */
    public function locked(PackLock $packLock): self
    {
        return new self($packLock, $this->installLines);
    }

    /**
     * @return list<string> Each line as printed.
     */
    public function texts(): array
    {
        return array_map(static fn (InstallLine $installLine): string => $installLine->text(), $this->installLines);
    }
}
