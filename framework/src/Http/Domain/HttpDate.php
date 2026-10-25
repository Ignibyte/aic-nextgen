<?php

declare(strict_types=1);

namespace AIC\Http\Domain;

use function array_map;
use function array_search;
use function checkdate;
use function gmdate;
use function gmmktime;
use function intdiv;
use function min;
use function preg_match;
use function strlen;

/** Parse the three HTTP date forms strictly in UTC and emit the canonical IMF-fixdate form. */
final readonly class HttpDate
{
    /** The complete time of day, with the leap-second value accepted by HTTP. */
    private const string CLOCK = '(?<hour>[0-9]{2}):(?<minute>[0-9]{2}):(?<second>[0-9]{2})';

    /** Both modern and obsolete date forms share the same named component fields. */
    private const array PATTERNS = [
        '#^(?<weekday>[A-Z][a-z]{2}), (?<day>[0-9]{2}) (?<month>[A-Z][a-z]{2}) (?<year>[0-9]{4}) '
            . self::CLOCK . ' GMT\\z#',
        '#^(?<weekday>Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday), '
            . '(?<day>[0-9]{2})-(?<month>[A-Z][a-z]{2})-(?<year>[0-9]{2}) '
            . self::CLOCK . ' GMT\\z#',
        '#^(?<weekday>[A-Z][a-z]{2}) (?<month>[A-Z][a-z]{2}) (?<day>[ 0-9][0-9]) '
            . self::CLOCK . ' (?<year>[0-9]{4})\\z#',
    ];

    /**
     * @param string $value The complete field value, already stripped of outer optional whitespace by its caller.
     * @param int    $now   The response's one sampled UTC origination time, used for obsolete two-digit years.
     *
     * @return int|null Its UTC timestamp, or null for invalid grammar, date or weekday.
     */
    public function parse(string $value, int $now): ?int
    {
        foreach (self::PATTERNS as $index => $pattern) {
            $matches = [];

            if (preg_match($pattern, $value, $matches) !== 1) {
                continue;
            }

            $parts = $this->components($matches, $index === 2);

            return $this->timestamp(
                $this->part($parts, 0),
                $this->month($this->part($parts, 2)),
                (int) $this->part($parts, 1),
                $this->part($parts, 3),
                (int) $this->part($parts, 4),
                (int) $this->part($parts, 5),
                (int) $this->part($parts, 6),
                $now,
            );
        }

        return null;
    }

    /**
     * @param int $timestamp A UTC timestamp.
     *
     * @return string The canonical HTTP wire date.
     */
    public function format(int $timestamp): string
    {
        return gmdate('D, d M Y H:i:s', $timestamp) . ' GMT';
    }

    /**
     * @param string $weekday  The captured weekday.
     * @param int    $month    Its calendar month.
     * @param int    $day      The captured day.
     * @param string $yearText Its two- or four-digit year.
     * @param int    $hour     The unsigned hour.
     * @param int    $minute   The unsigned minute.
     * @param int    $second   The unsigned second.
     * @param int    $now      The sampled response origination time.
     *
     * @return int|null Its validated timestamp.
     */
    private function timestamp(
        string $weekday,
        int $month,
        int $day,
        string $yearText,
        int $hour,
        int $minute,
        int $second,
        int $now,
    ): ?int {
        $components = ($month * 100 + $day) * 1_000_000 + $hour * 10_000 + $minute * 100 + $second;
        $year = $this->year($yearText, $components, $now);

        if (!$this->calendar($month, $day, $year)) {
            return null;
        }

        if (!$this->clock($hour, $minute, $second)) {
            return null;
        }

        $timestamp = gmmktime($hour, $minute, min($second, 59), $month, $day, $year);

        if ($timestamp === false || !$this->weekday($weekday, $timestamp)) {
            return null;
        }

        return $timestamp + ($second === 60 ? 1 : 0);
    }

    /**
     * @param int $month The calendar month.
     * @param int $day   The day of that month.
     * @param int $year  The complete year after obsolete-date interpretation.
     *
     * @return bool Whether the date is valid under HTTP's Internet Message Format calendar semantics.
     */
    private function calendar(int $month, int $day, int $year): bool
    {
        return $year >= 1_900 && checkdate($month, $day, $year);
    }

    /**
     * @param string $value      The complete four-digit year or obsolete two-digit year.
     * @param int    $components Its month/day/time as an unsigned MMDDhhmmss integer.
     * @param int    $now        The response origination time.
     *
     * @return int The year, applying HTTP's fifty-year rule to obsolete dates.
     */
    private function year(string $value, int $components, int $now): int
    {
        if (strlen($value) === 4) {
            return (int) $value;
        }

        $current = (int) gmdate('Y', $now);
        $year = intdiv($current, 100) * 100 + (int) $value;

        $future = $year > $current + 50 || ($year === $current + 50 && $components > (int) gmdate('mdHis', $now));

        return $future
            ? $year - 100
            : $year;
    }

    /**
     * @param string $value     The matched short or full English weekday.
     * @param int    $timestamp Its date before leap-second normalization.
     *
     * @return bool Whether the weekday agrees with the actual date.
     */
    private function weekday(string $value, int $timestamp): bool
    {
        return gmdate(strlen($value) === 3 ? 'D' : 'l', $timestamp) === $value;
    }

    /**
     * @param int $hour   The hour component.
     * @param int $minute The minute component.
     * @param int $second The second component, including a possible leap second.
     *
     * @return bool Whether the already unsigned components fit a UTC clock.
     */
    private function clock(int $hour, int $minute, int $second): bool
    {
        return $hour <= 23 && $minute <= 59 && $second <= 60;
    }

    /**
     * @param string $value The exact English abbreviated month.
     *
     * @return int Its calendar month, or zero for checkdate to reject.
     */
    private function month(string $value): int
    {
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $index = array_search($value, $months, true);

        return $index === false
            ? 0
            : $index + 1;
    }

    /**
     * @param array<int|string, string> $parts The numbered captures or normalized date components.
     * @param int                       $index The date component to read.
     *
     * @return string The captured component, or empty when unavailable.
     */
    private function part(array $parts, int $index): string
    {
        return $parts[$index] ?? '';
    }

    /**
     * @param array<int|string, string> $matches The complete regex capture result from either runtime.
     * @param bool                      $asctime Whether month/day/year appear in the obsolete asctime order.
     *
     * @return list<string> Weekday, day, month, year and clock components, independent of named-capture support.
     */
    private function components(array $matches, bool $asctime): array
    {
        $order = $asctime
            ? [1, 3, 2, 7, 4, 5, 6]
            : [1, 2, 3, 4, 5, 6, 7];

        return array_map(fn (int $index): string => $this->part($matches, $index), $order);
    }
}
