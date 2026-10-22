<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

use InvalidArgumentException;

use function preg_match;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function substr;

/**
 * A house rule (#104): a PHP file the engine runs on the run's change, base
 * against head, with the sentence it holds the run to. Its probe sits beside
 * it, in `<file minus .php>.probe/`.
 *
 * @api
 */
final readonly class HouseRule
{
    /**
     * @param string $id      Its name, `[a-z0-9-]+`, unique among the project's rules.
     * @param string $rule    The sentence it holds the run to.
     * @param string $run     Its PHP file, relative to the project.
     * @param int    $timeout Seconds it may take before it is stopped.
     *
     * @throws InvalidArgumentException When the id or the path is not a rule's.
     */
    public function __construct(
        public string $id,
        public string $rule,
        public string $run,
        public int $timeout = 60,
    ) {
        if (preg_match('/^[a-z0-9-]+$/', $id) !== 1) {
            throw new InvalidArgumentException('a house rule\'s id is [a-z0-9-]+; got "' . $id . '".');
        }

        if (!str_ends_with($run, '.php') || str_starts_with($run, '/') || str_contains('/' . $run . '/', '/../')) {
            throw new InvalidArgumentException('house rule ' . $id . ' runs a .php file inside the project; got "' . $run . '".');
        }
    }

    /**
     * @return string Its probe directory, relative to the project.
     */
    public function probe(): string
    {
        return substr($this->run, 0, -4) . '.probe';
    }
}
