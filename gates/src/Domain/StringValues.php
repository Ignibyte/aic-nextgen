<?php

declare(strict_types=1);

namespace Gates\Domain;

use function array_keys;
use function is_string;

/**
 * Keeps only validated strings from an external collection, in their original order.
 */
trait StringValues
{
    /**
     * @template T
     *
     * @param array<array-key, T> $values External values.
     *
     * @return list<string> Their string members.
     */
    private static function strings(array $values): array
    {
        $strings = [];

        foreach (array_keys($values) as $key) {
            if (!is_string($values[$key])) {
                continue;
            }

            $strings[] = $values[$key];
        }

        return $strings;
    }
}
