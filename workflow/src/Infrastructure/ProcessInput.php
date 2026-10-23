<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use function fwrite;
use function restore_error_handler;
use function set_error_handler;
use function strlen;
use function substr;

/** Advances one pipe writer without blocking the concurrent output readers. */
final class ProcessInput
{
    private int $offset = 0;

    /** @param string $input Bytes to write, at most one pipe-sized chunk per turn. */
    public function __construct(private readonly string $input)
    {
    }

    /** @return bool Whether every input byte was delivered. */
    public function complete(): bool
    {
        return $this->offset === strlen($this->input);
    }

    /**
     * @param array{0: resource} $pipe The writable pipe.
     *
     * @return bool Whether it can be closed, including a child that stopped reading.
     */
    public function write(array $pipe): bool
    {
        // EPIPE is an expected lifecycle result, not a warning on the caller's output.
        set_error_handler(static fn (): bool => true);

        try {
            $written = fwrite($pipe[0], substr($this->input, $this->offset, 65_536));
        } finally {
            restore_error_handler();
        }

        $this->offset += $written === false
            ? 0
            : $written;

        return $written === false || $this->complete();
    }
}
