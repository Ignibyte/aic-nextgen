<?php

declare(strict_types=1);

namespace Gates\Domain;

use function addcslashes;
use function max;
use function min;
use function sprintf;
use function strlen;
use function strspn;
use function substr;

/**
 * How two answers' bodies differ (gate:23, #217): both sizes, the byte where
 * they part and what each holds there, so a difference seen once names its
 * cause (a body cut short, another build's asset, an error page).
 */
final readonly class BodyDifference
{
    /**
     * Bytes an excerpt shows before the first difference.
     */
    private const int BEFORE = 24;

    /**
     * Bytes an excerpt shows from the first difference on.
     */
    private const int AFTER = 40;

    /**
     * @param string $native The compiled site's body.
     * @param string $php    The interpreted site's body.
     */
    public function __construct(
        private string $native,
        private string $php,
    ) {
    }

    /**
     * @return string One line: the sizes, the first differing byte and an escaped excerpt of each side there.
     */
    public function line(): string
    {
        $shorter = min(strlen($this->native), strlen($this->php));
        $parted = strspn(substr($this->native, 0, $shorter) ^ substr($this->php, 0, $shorter), "\0");
        $from = max(0, $parted - self::BEFORE);

        return sprintf(
            'the bodies differ: %d bytes natively, %d in PHP; from byte %d, natively "%s" and in PHP "%s"',
            strlen($this->native),
            strlen($this->php),
            $parted,
            self::excerpt($this->native, $from),
            self::excerpt($this->php, $from),
        );
    }

    /**
     * @param string $body A body.
     * @param int    $from Where the excerpt starts.
     *
     * @return string Its bytes there, control and non-ASCII bytes, quotes and backslashes escaped.
     */
    private static function excerpt(string $body, int $from): string
    {
        return addcslashes(substr($body, $from, self::BEFORE + self::AFTER), "\0..\37\"\\\177..\377");
    }
}
