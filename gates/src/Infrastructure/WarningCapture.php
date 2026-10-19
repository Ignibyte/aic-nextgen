<?php

declare(strict_types=1);

namespace Gates\Infrastructure;

/**
 * Mutable slot used by SystemProcessRunner to capture the most recent
 * warning message produced by the underlying process-spawn call.
 *
 * The closure passed to set_error_handler calls record(); the caller
 * reads the value back via read() after the protected region. This
 * class exists only because Slevomat's "no inheriting variables by
 * reference into closures" rule forbids the natural use (&$captured)
 * idiom — wrapping the slot in a value object lets the closure mutate
 * shared state through an object handle without the by-ref dance.
 */
final class WarningCapture
{
    private string $message = '';

    /**
     * Record the latest captured warning message.
     *
     * @param string $message Warning text emitted by the masked operation.
     */
    public function record(string $message): void
    {
        $this->message = $message;
    }

    /**
     * Read the captured message back out.
     */
    public function read(): string
    {
        return $this->message;
    }
}
