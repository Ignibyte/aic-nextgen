<?php

declare(strict_types=1);

namespace App\Greeting\Command;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Module\Attribute\Tagged;
use App\Greeting\Service\Greeter;
use Override;

/**
 * `aic greet [name]`: one greeting per Greeter plugin, in tag order; the name
 * defaults to "world".
 */
final readonly class Greet implements Command
{
    /**
     * @param list<object> $greeters The Greeter plugins, in tag order (`#[Tagged]`).
     */
    public function __construct(
        #[Tagged(Greeter::class)]
        private array $greeters,
    ) {
    }

    /**
     * @param CommandInput $commandInput The name, first.
     *
     * @return CommandResponse The greetings.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        $name = $commandInput->argument(0, 'world');
        $lines = [];

        foreach ($this->greeters as $greeter) {
            if (!$greeter instanceof Greeter) {
                continue;
            }

            $lines[] = $greeter->greet($name);
        }

        return new CommandResult($lines, 0);
    }
}
