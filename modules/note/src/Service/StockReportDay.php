<?php

declare(strict_types=1);

namespace App\Note\Service;

use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;
use function is_string;
use function preg_match;

/**
 * Reads the day the stock report asks for (#191), so the report page needs
 * nothing of the stock list's (#192).
 */
final readonly class StockReportDay
{
    /**
     * @param ServerRequestInterface $serverRequest The request: an optional `day`.
     *
     * @return string The day the report asks for, `YYYY-MM-DD`; empty when none is, or it is malformed.
     */
    public function day(ServerRequestInterface $serverRequest): string
    {
        $query = $serverRequest->getQueryParams();
        $day = array_key_exists('day', $query) && is_string($query['day'])
            ? $query['day']
            : '';

        return self::isDay($day)
            ? $day
            : '';
    }

    /**
     * @param string $day A day as sent.
     *
     * @return bool Whether it is `YYYY-MM-DD`.
     */
    private static function isDay(string $day): bool
    {
        return preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $day) === 1;
    }
}
