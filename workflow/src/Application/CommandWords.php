<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Controller\Contracts\CommandInput;

use function implode;

/**
 * A command's arguments from a position on, as one text (#117): an answer
 * or a question typed with or without quotes reads the same.
 */
final readonly class CommandWords
{
    /**
     * What a missing argument reads as; no argument holds a NUL byte.
     */
    private const string MISSING = "\0";

    /**
     * @param CommandInput $commandInput The arguments.
     * @param int          $start        The first position to take, from 0.
     *
     * @return string The arguments from there on, joined by a space; empty when there are none.
     */
    public function from(CommandInput $commandInput, int $start): string
    {
        $words = [];
        $index = $start;
        $word = $commandInput->argument($index, self::MISSING);

        while ($word !== self::MISSING) {
            $words[] = $word;
            $index += 1;
            $word = $commandInput->argument($index, self::MISSING);
        }

        return implode(' ', $words);
    }
}
