<?php

declare(strict_types=1);

namespace AIC\Http\Domain;

use function is_int;
use function mb_ltrim;
use function preg_replace_callback;
use function rawurlencode;
use function str_starts_with;

/**
 * RFC 3986 component normalisation for Uri: default-port suppression,
 * percent-encoding, and path and authority alignment. A trait keeps Uri under
 * PHPMD's class-length limit.
 */
trait EncodesUriComponents
{
    /** Default ports suppressed during URI normalisation (RFC 3986 §3.2.3). */
    private const array DEFAULT_PORTS = [
        'http' => 80,
        'https' => 443,
    ];

    /**
     * Suppress the scheme's default port (PSR-7 §4.3 / RFC 3986 §3.2.3).
     *
     * @param string   $scheme Lower-cased scheme (`''` allowed).
     * @param int|null $port   Raw port value or `null`.
     */
    private static function filterPort(string $scheme, ?int $port): ?int
    {
        $default = self::DEFAULT_PORTS[$scheme] ?? null;

        if (is_int($default) && $port === $default) {
            return null;
        }

        return $port;
    }

    /**
     * Percent-encode characters not in `$allowed`, leaving existing percent-
     * triplets intact (PSR-7 §4.1 — no double-encoding).
     *
     * @param string $value   Raw component value to encode.
     * @param string $allowed Character class fragment of bytes to leave un-encoded.
     */
    private static function encode(string $value, string $allowed): string
    {
        return preg_replace_callback(
            '/(?:[^' . $allowed . '%]++|%(?![A-Fa-f0-9]{2}))/',
            static fn (array $matches): string => rawurlencode($matches[0] ?? ''),
            $value,
        ) ?? $value;
    }

    /**
     * Normalise a path against the presence of an authority component
     * (RFC 3986 §5.3): when an authority exists, a non-empty path MUST start
     * with `/`; with no authority, a leading `//` is collapsed.
     *
     * @param string $path         Path component to normalise.
     * @param bool   $hasAuthority Whether the URI carries an authority component.
     */
    private static function normalizePath(string $path, bool $hasAuthority): string
    {
        if ($path === '') {
            return '';
        }

        if ($hasAuthority) {
            return $path[0] === '/'
                ? $path
                : '/' . $path;
        }

        if (str_starts_with($path, '//')) {
            return '/' . mb_ltrim($path, '/');
        }

        return $path;
    }
}
