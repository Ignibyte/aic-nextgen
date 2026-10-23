<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use function posix_getpgid;
use function posix_getpgrp;
use function posix_getsid;
use function posix_kill;

/** Signals only an unreaped helper or the session group it verifiably owns. */
final class ProcessGroup
{
    private bool $owned = false;

    /** @param int $pid The helper's PID, reserved until its final reap. */
    public function __construct(private readonly int $pid)
    {
    }

    /** @return bool Whether READY identifies a separate session led by this helper. */
    public function claim(): bool
    {
        $this->owned = $this->pid > 1 && $this->pid !== posix_getpgrp()
            && posix_getpgid($this->pid) === $this->pid && posix_getsid($this->pid) === $this->pid;

        return $this->owned;
    }

    /** Send TERM to the owned group; before ACK only the helper can exist. */
    public function terminate(): void
    {
        $this->signal(15);
    }

    /** Force termination before reaping; never call after reaping. */
    public function force(): void
    {
        $this->signal(9);
    }

    /** @param int $signal The POSIX signal number. */
    private function signal(int $signal): void
    {
        if ($this->pid <= 1) {
            return;
        }

        posix_kill($this->owned ? -$this->pid : $this->pid, $signal);
    }
}
