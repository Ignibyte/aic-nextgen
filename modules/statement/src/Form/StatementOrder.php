<?php

declare(strict_types=1);

namespace App\Statement\Form;

use function checkdate;
use function mb_strlen;
use function preg_match;

/**
 * An order for a statement (#336), the payload the report worker's
 * `statement` job reads field by field: the account it is for and its date.
 */
final readonly class StatementOrder
{
    /**
     * @param string $account The account, 1 to 80 characters.
     * @param string $date    Its date, as `2026-11-26`.
     */
    public function __construct(
        public string $account,
        public string $date,
    ) {
    }

    /**
     * @return bool Whether the worker can make it: an account of 1 to 80 characters and a real date.
     */
    public function valid(): bool
    {
        if ($this->account === '' || mb_strlen($this->account) > 80) {
            return false;
        }

        if (preg_match('/^(\\d{4})-(\\d{2})-(\\d{2})$/', $this->date, $parts) !== 1) {
            return false;
        }

        [, $year, $month, $day] = $parts;

        return checkdate((int) $month, (int) $day, (int) $year);
    }

    /**
     * @return array<string, string> The payload, as the job reads it.
     */
    public function values(): array
    {
        return ['account' => $this->account, 'date' => $this->date];
    }
}
