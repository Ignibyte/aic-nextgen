<?php

declare(strict_types=1);

namespace AIC\Console\Infrastructure;

use AIC\Controller\Contracts\CommandWriter;
use Override;

use function fwrite;

use const STDOUT;

/**
 * Writes a command's output to the process's standard output, a line at a time: what a compiled console program
 * (`bootstrap/socket.php`, #32) hands the console, where `bin/aic` uses a class of its own.
 *
 * @api
 */
final readonly class StdoutCommandWriter implements CommandWriter
{
    /**
     * @param string $text One line, without its line break.
     */
    #[Override]
    public function line(string $text): void
    {
        fwrite(STDOUT, $text . "\n");
    }
}
