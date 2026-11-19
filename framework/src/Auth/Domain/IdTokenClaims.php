<?php

declare(strict_types=1);

namespace AIC\Auth\Domain;

use AIC\Auth\Contracts\SignInRefusedException;
use AIC\Session\Domain\StringMapJson;

use function array_key_exists;
use function base64_decode;
use function count;
use function explode;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function strtr;

/**
 * The claims of an id token (#313), read from its payload and checked as
 * OpenID Connect Core 3.1.3.7 asks: the issuer, the audience (and `azp` when
 * there are several), the expiry and the nonce. The signature is not
 * checked: the client took the token straight from the provider's token
 * endpoint over TLS, which 3.1.3.7 item 6 accepts in its place; RS256 waits
 * on asymmetric openssl in the compiler (#325).
 */
final readonly class IdTokenClaims
{
    /**
     * Each string claim, by name.
     *
     * @var array<string, string>
     */
    private array $strings;

    /**
     * The `exp` claim, in seconds since the epoch; 0 when absent.
     */
    private int $expiry;

    /**
     * The audience, one or more client ids.
     *
     * @var list<string>
     */
    private array $aud;

    /**
     * @param string        $idToken       A compact JWT: header, payload and signature, base64url, joined by `.`.
     * @param StringMapJson $stringMapJson Reads the string claims.
     *
     * @throws SignInRefusedException When it is not three parts or its payload is not a JSON object.
     */
    public function __construct(string $idToken, StringMapJson $stringMapJson = new StringMapJson())
    {
        $parts = explode('.', $idToken);
        $payload = count($parts) === 3
            ? base64_decode(strtr($parts[1], '-_', '+/'), true)
            : false;

        if (!is_string($payload) || !is_array(json_decode($payload, true))) {
            throw new SignInRefusedException('The provider sent an id token that cannot be read.');
        }

        $strings = $stringMapJson->decode($payload);
        $audience = $this->audience($payload);

        if (array_key_exists('aud', $strings)) {
            $audience[] = $strings['aud'];
        }

        $this->strings = $strings;
        $this->expiry = $this->expiry($payload);
        $this->aud = $audience;
    }

    /**
     * @param OidcProvider $oidcProvider The provider it came from.
     * @param string       $name         The provider's name.
     * @param string       $nonce        The nonce the sign-in sent.
     * @param int          $now          The time, in seconds since the epoch.
     *
     * @throws SignInRefusedException When a claim fails its check.
     *
     * @return OidcIdentity Who it names.
     */
    public function identity(OidcProvider $oidcProvider, string $name, string $nonce, int $now): OidcIdentity
    {
        $subject = $this->strings['sub'] ?? '';
        $refusal = match (true) {
            ($this->strings['iss'] ?? '') !== $oidcProvider->issuer => 'it names another issuer',
            !$this->forClient($oidcProvider->clientId) => 'it is for another client',
            $this->expiry <= $now => 'it has expired',
            $nonce === '' || ($this->strings['nonce'] ?? '') !== $nonce => "its nonce is not this sign-in's",
            $subject === '' => 'it names no subject',
            default => '',
        };

        if ($refusal !== '') {
            throw new SignInRefusedException("The provider's id token was refused: " . $refusal . '.');
        }

        return new OidcIdentity($name, $subject, $this->strings['email'] ?? '');
    }

    /**
     * @param string $clientId The app's client id at the provider.
     *
     * @return bool Whether the audience holds it and, when there are several, `azp` names it.
     */
    private function forClient(string $clientId): bool
    {
        return in_array($clientId, $this->aud, true)
            && (count($this->aud) === 1 || ($this->strings['azp'] ?? '') === $clientId);
    }

    /**
     * @param string $payload The payload's JSON object.
     *
     * @return int Its `exp` claim when that is an integer; 0 otherwise.
     */
    private function expiry(string $payload): int
    {
        $decoded = json_decode($payload, true);

        if (!is_array($decoded) || !array_key_exists('exp', $decoded) || !is_int($decoded['exp'])) {
            return 0;
        }

        return $decoded['exp'];
    }

    /**
     * @param string $payload The payload's JSON object.
     *
     * @return list<string> The audience when `aud` is a list; a single `aud` is one of the strings.
     */
    private function audience(string $payload): array
    {
        $decoded = json_decode($payload, true);

        if (!is_array($decoded) || !array_key_exists('aud', $decoded) || !is_array($decoded['aud'])) {
            return [];
        }

        return $this->listed((string) json_encode($decoded['aud']));
    }

    /**
     * @param string $list A JSON list.
     *
     * @return list<string> Its string members, in order.
     */
    private function listed(string $list): array
    {
        $decoded = json_decode($list, true);
        $members = [];

        if (!is_array($decoded)) {
            return $members;
        }

        foreach ($decoded as $member) {
            if (!is_string($member)) {
                continue;
            }

            $members[] = $member;
        }

        return $members;
    }
}
