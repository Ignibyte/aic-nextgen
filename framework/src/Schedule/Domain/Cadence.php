<?php

declare(strict_types=1);

namespace AIC\Schedule\Domain;

use InvalidArgumentException;

use function array_all;
use function array_any;
use function array_map;
use function count;
use function explode;
use function gmdate;
use function in_array;
use function preg_match;
use function str_starts_with;
use function substr;

/**
 * When a schedule fires (#47): five cron fields in UTC, minute, hour, day of
 * month, month and weekday (0 is Sunday). Each field is `*`, a number, `*\/n`
 * or a comma list of numbers, and a minute matches when every field does.
 */
final readonly class Cadence
{
    /**
     * Each field's smallest and largest value.
     */
    private const array RANGES = [[0, 59], [0, 23], [1, 31], [1, 12], [0, 6]];

    /**
     * The fields.
     *
     * @var list<string>
     */
    private array $fields;

    /**
     * @param string $expression Five fields separated by single spaces, such as `*\/5 * * * *`.
     *
     * @throws InvalidArgumentException When it is not five valid fields.
     */
    public function __construct(
        public string $expression,
    ) {
        $fields = explode(' ', $expression);

        if (count($fields) !== 5) {
            throw new InvalidArgumentException('A cadence is five fields: "' . $expression . '".');
        }

        foreach ($fields as $index => $field) {
            self::check($field, self::RANGES[$index][0], self::RANGES[$index][1], $expression);
        }

        $this->fields = $fields;
    }

    /**
     * @param int $timestamp A Unix time.
     *
     * @return bool Whether its minute, in UTC, is one of the cadence's.
     */
    public function matches(int $timestamp): bool
    {
        $values = array_map(intval(...), explode(' ', gmdate('i G j n w', $timestamp)));

        // An instance method: elephc refuses `self::` inside a closure.
        return array_all($this->fields, fn (string $field, int $index): bool => $this->fieldMatches($field, $values[$index]));
    }

    /**
     * @param string $field A valid field.
     * @param int    $value The minute's value for it.
     *
     * @return bool Whether the field allows the value.
     */
    private function fieldMatches(string $field, int $value): bool
    {
        if ($field === '*') {
            return true;
        }

        if (str_starts_with($field, '*/')) {
            return $value % (int) substr($field, 2) === 0;
        }

        return in_array($value, array_map(intval(...), explode(',', $field)), true);
    }

    /**
     * @param string $field      A field.
     * @param int    $min        Its smallest value.
     * @param int    $max        Its largest value.
     * @param string $expression The whole cadence, for the message.
     *
     * @throws InvalidArgumentException When the field is malformed or out of range.
     */
    private static function check(string $field, int $min, int $max, string $expression): void
    {
        if ($field === '*' || preg_match('#^\\*/[1-9]\\d*$#', $field) === 1) {
            return;
        }

        $numbers = preg_match('/^\\d+(,\\d+)*$/', $field) === 1
            ? array_map(intval(...), explode(',', $field))
            : [-1];

        if (array_any($numbers, static fn (int $number): bool => $number < $min || $number > $max)) {
            throw new InvalidArgumentException('Cadence "' . $expression . '": "' . $field . '" is not a valid field.');
        }
    }
}
