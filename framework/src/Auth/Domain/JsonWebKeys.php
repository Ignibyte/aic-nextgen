<?php

declare(strict_types=1);

namespace AIC\Auth\Domain;

use AIC\Auth\Contracts\SignInRefusedException;
use AIC\Session\Domain\StringMapJson;

use function array_key_exists;
use function base64_decode;
use function count;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function strtr;

/**
 * A provider's JSON Web Key Set (#343), read from its `jwks_uri` answer:
 * `{"keys": [...]}`, each key's string members kept. The key a token's
 * header names is found by its `kid`, or, when the header names none, the
 * one key of the type its algorithm needs.
 */
final readonly class JsonWebKeys
{
    /**
     * The keys, in the set's order.
     *
     * @var list<JsonWebKey>
     */
    private array $keys;

    /**
     * @param string        $json          The key set's JSON.
     * @param StringMapJson $stringMapJson Reads each key's string members.
     *
     * @throws SignInRefusedException When it is not a JSON object with a list of keys.
     */
    public function __construct(string $json, StringMapJson $stringMapJson = new StringMapJson())
    {
        $keys = [];

        foreach (self::encoded($json) as $encoded) {
            $keys[] = self::parsed($stringMapJson->decode($encoded));
        }

        $this->keys = $keys;
    }

    /**
     * @param string $kid The id the token's header names; empty when it names none.
     * @param string $kty The type its algorithm needs, `RSA` or `EC`.
     *
     * @throws SignInRefusedException When not one key has that id, or, with no id, not one key has that type.
     *
     * @return JsonWebKey The key.
     */
    public function key(string $kid, string $kty): JsonWebKey
    {
        $found = $kid === ''
            ? $this->typed($kty)
            : $this->named($kid);

        if (count($found) !== 1) {
            throw new SignInRefusedException("The provider's id token was refused: none of its keys signed it.");
        }

        return $found[0];
    }

    /**
     * @param string $kid A key's id.
     *
     * @return list<JsonWebKey> The keys with that id.
     */
    private function named(string $kid): array
    {
        $named = [];

        foreach ($this->keys as $key) {
            if ($key->kid !== $kid) {
                continue;
            }

            $named[] = $key;
        }

        return $named;
    }

    /**
     * @param string $kty A key type.
     *
     * @return list<JsonWebKey> The keys of that type.
     */
    private function typed(string $kty): array
    {
        $typed = [];

        foreach ($this->keys as $key) {
            if ($key->kty !== $kty) {
                continue;
            }

            $typed[] = $key;
        }

        return $typed;
    }

    /**
     * @param string $json The key set's JSON.
     *
     * @throws SignInRefusedException When it is not a JSON object with a list of keys.
     *
     * @return list<string> Each key object, as JSON.
     */
    private static function encoded(string $json): array
    {
        $decoded = json_decode($json, true);

        if (!is_array($decoded) || !array_key_exists('keys', $decoded) || !is_array($decoded['keys'])) {
            throw new SignInRefusedException("The sign-in provider's keys cannot be read.");
        }

        return self::objects((string) json_encode($decoded['keys']));
    }

    /**
     * @param string $list A JSON list.
     *
     * @return list<string> Each of its members that is an object, as JSON.
     */
    private static function objects(string $list): array
    {
        $decoded = json_decode($list, true);
        $objects = [];

        if (!is_array($decoded)) {
            return $objects;
        }

        foreach ($decoded as $member) {
            if (!is_array($member)) {
                continue;
            }

            $objects[] = (string) json_encode($member);
        }

        return $objects;
    }

    /**
     * @param array<string, string> $members A key's string members.
     *
     * @return JsonWebKey The key, its components decoded.
     */
    private static function parsed(array $members): JsonWebKey
    {
        return new JsonWebKey(
            $members['kid'] ?? '',
            $members['kty'] ?? '',
            $members['alg'] ?? '',
            $members['crv'] ?? '',
            self::bytes($members['n'] ?? ''),
            self::bytes($members['e'] ?? ''),
            self::bytes($members['x'] ?? ''),
            self::bytes($members['y'] ?? ''),
        );
    }

    /**
     * @param string $base64url A base64url value, with or without padding.
     *
     * @return string Its bytes; empty when it is not base64url.
     */
    private static function bytes(string $base64url): string
    {
        $bytes = base64_decode(strtr($base64url, '-_', '+/'), true);

        return is_string($bytes)
            ? $bytes
            : '';
    }
}
