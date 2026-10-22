<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Console;

use AIC\Controller\Contracts\CommandInput;
use AIC\Workflow\Domain\Console\CommandUsage;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_search;
use function array_slice;
use function count;
use function explode;
use function in_array;
use function str_contains;
use function str_starts_with;
use function substr;

/**
 * Splits a command line into positionals and flags and holds it to the
 * command's usage (#109, after Droost's `init --dry-run` lesson): an unknown
 * flag, a flag missing its value or given one it does not take, and too few
 * or too many positionals are refused, so nothing runs on a misread line. A
 * lone `--` ends the flags.
 */
final readonly class ConsoleArguments
{
    /**
     * The usage refusal's code, which the command turns into exit 2.
     */
    public const int USAGE = 2;

    /**
     * @param CommandInput $commandInput The command line.
     * @param CommandUsage $commandUsage What the command takes.
     *
     * @throws WorkflowRefusal With code 2, naming the problem and the usage line, when the line does not fit.
     *
     * @return ParsedArguments The positionals and the flags.
     */
    public function parse(CommandInput $commandInput, CommandUsage $commandUsage): ParsedArguments
    {
        [$words, $positionals] = self::halves($commandInput->arguments());
        $flags = [];

        foreach ($words as $word) {
            if (!str_starts_with($word, '--')) {
                $positionals[] = $word;

                continue;
            }

            [$name, $value] = self::flag($word, $commandUsage);
            $flags[$name] = $value;
        }

        self::count($positionals, $commandUsage);

        return new ParsedArguments($positionals, $flags);
    }

    /**
     * @param list<string> $words The command line.
     *
     * @return array{list<string>, list<string>} The words before a lone `--`, and those after it, which are all positionals.
     */
    private static function halves(array $words): array
    {
        $end = array_search('--', $words, true);

        return $end === false
            ? [$words, []]
            : [array_slice($words, 0, $end), array_slice($words, $end + 1)];
    }

    /**
     * @param list<string> $positionals  The positionals given.
     * @param CommandUsage $commandUsage What the command takes.
     *
     * @throws WorkflowRefusal With code 2 when there are too few or too many.
     */
    private static function count(array $positionals, CommandUsage $commandUsage): void
    {
        $count = count($positionals);

        if ($count < $commandUsage->required || $count > $commandUsage->required + $commandUsage->optional) {
            throw self::usage('it takes ' . self::takes($commandUsage) . ', not ' . $count, $commandUsage);
        }
    }

    /**
     * @param string       $word         One `--name` or `--name=value` word.
     * @param CommandUsage $commandUsage What the command takes.
     *
     * @throws WorkflowRefusal With code 2 when the command does not take the flag that way.
     *
     * @return array{string, string} The flag's name and its value.
     */
    private static function flag(string $word, CommandUsage $commandUsage): array
    {
        [$name, $value] = [...explode('=', substr($word, 2), 2), ''];
        $valued = in_array($name, $commandUsage->valued, true);
        $switch = $name === 'json' || in_array($name, $commandUsage->switches, true);

        return match (true) {
            $valued && $value !== '' => [$name, $value],
            $valued => throw self::usage('--' . $name . ' needs a value', $commandUsage),
            $switch && !str_contains($word, '=') => [$name, ''],
            $switch => throw self::usage('--' . $name . ' takes no value', $commandUsage),
            default => throw self::usage('--' . $name . ' is not a flag it takes', $commandUsage),
        };
    }

    /**
     * @param CommandUsage $commandUsage What the command takes.
     *
     * @return string How many positionals it takes, in words.
     */
    private static function takes(CommandUsage $commandUsage): string
    {
        $most = $commandUsage->required + $commandUsage->optional;

        return $commandUsage->optional === 0
            ? $most . ' argument(s)'
            : $commandUsage->required . ' to ' . $most . ' argument(s)';
    }

    /**
     * @param string       $why          What is wrong with the line.
     * @param CommandUsage $commandUsage What the command takes.
     *
     * @return WorkflowRefusal The refusal, with code 2 and the usage line.
     */
    private static function usage(string $why, CommandUsage $commandUsage): WorkflowRefusal
    {
        return new WorkflowRefusal($why . '. Usage: bin/aic ' . $commandUsage->line, self::USAGE);
    }
}
