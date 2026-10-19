<?php

declare(strict_types=1);

namespace AIC\Controller\Contracts;

/**
 * What a Command controller returns. Sealed: only the result shapes in
 * AIC\Controller\Domain implement it (a phpat guard), and each writes itself
 * through the CommandWriter.
 */
interface CommandResponse
{
    /**
     * @param CommandWriter $commandWriter Writes the output.
     *
     * @return int The exit code.
     */
    public function writeTo(CommandWriter $commandWriter): int;
}
