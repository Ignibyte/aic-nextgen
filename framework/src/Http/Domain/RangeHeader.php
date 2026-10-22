<?php

declare(strict_types=1);

namespace AIC\Http\Domain;

use function max;
use function min;
use function preg_match;
use function trim;

/**
 * One `Range` request header, resolved against a resource's size (RFC 9110
 * §14). Only a single byte range is honoured: `bytes=first-last`,
 * `bytes=first-` or `bytes=-suffix`. Anything else, a multi-range request
 * included, reads as the whole resource, which the RFC allows a server to do.
 */
final readonly class RangeHeader
{
    /**
     * @param string $line The header's value; empty when the request has none.
     */
    public function __construct(
        private string $line,
    ) {
    }

    /**
     * @param int $size The resource's size, in bytes.
     *
     * @return ByteRange What the request comes to.
     */
    public function resolve(int $size): ByteRange
    {
        $byteRange = new ByteRange(RangeKind::Whole, 0, max(0, $size - 1), $size);

        if (preg_match('/^bytes=(\\d*)-(\\d*)$/', trim($this->line), $match) !== 1) {
            return $byteRange;
        }

        // trim() hands elephc typed strings: it reads array elements as mixed.
        [$first, $last] = self::endpoints($match);

        if ($first === '') {
            return $last === ''
                ? $byteRange
                : self::suffix((int) $last, $size);
        }

        return self::fromFirst((int) $first, $last, $size, $byteRange);
    }

    /**
     * @param array<array-key, string> $captures The range pattern's captures.
     *
     * @return array{string, string} The first and last byte, empty when omitted.
     */
    private static function endpoints(array $captures): array
    {
        return [trim($captures[1] ?? ''), trim($captures[2] ?? '')];
    }

    /**
     * @param int $length The last bytes asked for.
     * @param int $size   The resource's size.
     *
     * @return ByteRange The resource's last `length` bytes, or unsatisfiable for none.
     */
    private static function suffix(int $length, int $size): ByteRange
    {
        if ($length === 0 || $size === 0) {
            return new ByteRange(RangeKind::Unsatisfiable, 0, 0, $size);
        }

        return new ByteRange(RangeKind::Part, max(0, $size - $length), $size - 1, $size);
    }

    /**
     * @param int       $first     The first byte asked for.
     * @param string    $last      The last byte asked for; empty for the end.
     * @param int       $size      The resource's size.
     * @param ByteRange $byteRange The whole-resource answer, for a range that is not valid.
     *
     * @return ByteRange The part, unsatisfiable when it starts past the end, or whole when it is invalid.
     */
    private static function fromFirst(int $first, string $last, int $size, ByteRange $byteRange): ByteRange
    {
        if ($first >= $size) {
            return new ByteRange(RangeKind::Unsatisfiable, 0, 0, $size);
        }

        if ($last !== '' && (int) $last < $first) {
            return $byteRange;
        }

        return new ByteRange(RangeKind::Part, $first, $last === '' ? $size - 1 : min((int) $last, $size - 1), $size);
    }
}
