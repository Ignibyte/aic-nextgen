<?php

declare(strict_types=1);

namespace AIC\Controller\Contracts;

/**
 * Writes a command's output. The entry script implements it over its output
 * stream, as the worker implements SapiSink.
 */
interface CommandWriter
{
    /**
     * @param string $text One line, without its line break.
     */
    public function line(string $text): void;
}
