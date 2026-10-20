<?php

declare(strict_types=1);

namespace AIC\Session\Domain;

use JsonException;

use function is_array;
use function is_string;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * A session's values to and from its stored payload, a JSON object of
 * strings. A payload that is not one decodes to no values.
 */
final readonly class SessionCodec
{
    /**
     * @param array<string, string> $values A session's values.
     *
     * @throws JsonException When they cannot be encoded.
     *
     * @return string The payload.
     */
    public function encode(array $values): string
    {
        return json_encode($values, JSON_THROW_ON_ERROR);
    }

    /**
     * @param string $payload A stored payload.
     *
     * @return array<string, string> Its string values by string key; nothing for anything else.
     */
    public function decode(string $payload): array
    {
        $decoded = json_decode($payload, true);
        $values = [];

        if (!is_array($decoded)) {
            return $values;
        }

        foreach ($decoded as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                continue;
            }

            $values[$key] = $value;
        }

        return $values;
    }
}
