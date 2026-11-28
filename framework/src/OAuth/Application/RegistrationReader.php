<?php

declare(strict_types=1);

namespace AIC\OAuth\Application;

use AIC\OAuth\Contracts\OAuthRefusedException;
use AIC\OAuth\Domain\RedirectUris;
use AIC\OAuth\Domain\RegistrationRequest;

use function array_diff;
use function array_filter;
use function array_is_list;
use function array_key_exists;
use function array_keys;
use function array_unique;
use function array_values;
use function explode;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;

/**
 * Reads a client's registration request (RFC 7591 section 2, #352) from its
 * JSON body and checks it: the redirect URIs (each one the `RedirectUris`
 * rule takes; required for the code grant), how it authenticates at the
 * token endpoint (`none` makes a public client, `client_secret_basic`, the
 * default, or `client_secret_post` a confidential one), its grant types
 * (`authorization_code`, the default, `refresh_token`, and
 * `client_credentials` for a confidential client only), its response types
 * (only `code`) and its scopes, all within what the server lets a registered
 * client hold. Other metadata (a name, a logo, contacts) is not kept, so
 * nothing a client says about itself reaches the consent page.
 */
final readonly class RegistrationReader
{
    /**
     * How a client may authenticate at the token endpoint.
     */
    private const array AUTH_METHODS = ['none', 'client_secret_basic', 'client_secret_post'];

    /**
     * The grants a registered client may name.
     */
    private const array GRANT_TYPES = ['authorization_code', 'refresh_token', 'client_credentials'];

    /**
     * @param RedirectUris $redirectUris The redirect URI rule.
     */
    public function __construct(
        private RedirectUris $redirectUris = new RedirectUris(),
    ) {
    }

    /**
     * @param string       $body               The request's JSON body.
     * @param list<string> $registrationScopes The most a registered client may hold.
     *
     * @throws OAuthRefusedException When the body is not a JSON object, a redirect URI is refused
     *                               (`invalid_redirect_uri`) or other metadata is (`invalid_client_metadata`).
     *
     * @return RegistrationRequest The checked request.
     */
    public function read(string $body, array $registrationScopes): RegistrationRequest
    {
        $members = self::members($body);
        $authMethod = self::text($members, 'token_endpoint_auth_method', 'client_secret_basic', self::AUTH_METHODS);
        $grantTypes = self::grantTypes($members, $authMethod !== 'none');
        self::responseTypes($members);

        return new RegistrationRequest(
            $this->redirectUris($members, in_array('authorization_code', $grantTypes, true)),
            $authMethod,
            $grantTypes,
            self::scopes(self::text($members, 'scope', '', []), $registrationScopes),
        );
    }

    /**
     * @param array<string, string> $decoded  The body's members, as JSON.
     * @param bool                  $required Whether the client uses the code grant, which needs one.
     *
     * @throws OAuthRefusedException When one is missing or refused.
     *
     * @return list<string> The redirect URIs.
     */
    private function redirectUris(array $decoded, bool $required): array
    {
        $uris = self::words($decoded, 'redirect_uris', []);

        if ($required && $uris === []) {
            throw new OAuthRefusedException('invalid_redirect_uri', 'The code grant needs a redirect URI.');
        }

        foreach ($uris as $uri) {
            if (!$this->redirectUris->accepts($uri)) {
                throw new OAuthRefusedException(
                    'invalid_redirect_uri',
                    'A redirect URI must be https (http only on a loopback host) with no fragment: ' . $uri . '.',
                );
            }
        }

        return array_values(array_unique($uris));
    }

    /**
     * @param string $body The request's JSON body.
     *
     * @throws OAuthRefusedException When it is not a JSON object.
     *
     * @return array<string, string> Each member, as JSON.
     */
    private static function members(string $body): array
    {
        $decoded = json_decode($body, true);

        if (!is_array($decoded) || array_is_list($decoded)) {
            throw self::refused('The body is not a JSON object.');
        }

        $members = [];

        foreach (array_keys($decoded) as $name) {
            $members[(string) $name] = (string) json_encode($decoded[$name]);
        }

        return $members;
    }

    /**
     * @param array<string, string> $decoded  The body's members, as JSON.
     * @param string                $name     A member.
     * @param string                $default  Its value when absent.
     * @param list<string>          $accepted The values it may take; none for any string.
     *
     * @throws OAuthRefusedException When it is not a string, or not one accepted.
     *
     * @return string Its value.
     */
    private static function text(array $decoded, string $name, string $default, array $accepted): string
    {
        if (!array_key_exists($name, $decoded)) {
            return $default;
        }

        $value = json_decode($decoded[$name], true);

        if (!is_string($value) || ($accepted !== [] && !in_array($value, $accepted, true))) {
            throw self::refused('The ' . $name . ' is not one this server takes.');
        }

        return $value;
    }

    /**
     * @param array<string, string> $decoded The body's members, as JSON.
     * @param string                $name    A member holding a list of strings.
     * @param list<string>          $default Its strings when it is absent.
     *
     * @throws OAuthRefusedException When it is not a list of strings.
     *
     * @return list<string> Its strings, or the default when absent.
     */
    private static function words(array $decoded, string $name, array $default): array
    {
        if (!array_key_exists($name, $decoded)) {
            return $default;
        }

        $value = json_decode($decoded[$name], true);

        if (!is_array($value)) {
            throw self::refused('The ' . $name . ' must be a list of strings.');
        }

        $words = [];

        foreach ($value as $word) {
            if (!is_string($word)) {
                throw self::refused('The ' . $name . ' must be a list of strings.');
            }

            $words[] = $word;
        }

        return $words;
    }

    /**
     * @param array<string, string> $decoded      The body's members, as JSON.
     * @param bool                  $confidential Whether the client authenticates with a secret.
     *
     * @throws OAuthRefusedException When a grant is unknown, or a public client names client credentials.
     *
     * @return list<string> The grant types.
     */
    private static function grantTypes(array $decoded, bool $confidential): array
    {
        $grantTypes = array_values(array_unique(self::words($decoded, 'grant_types', ['authorization_code'])));

        if ($grantTypes === [] || array_diff($grantTypes, self::GRANT_TYPES) !== []) {
            throw self::refused('The grant_types name a grant this server does not issue.');
        }

        if (!$confidential && in_array('client_credentials', $grantTypes, true)) {
            throw self::refused('A public client cannot use the client credentials grant.');
        }

        return $grantTypes;
    }

    /**
     * @param array<string, string> $decoded The body's members, as JSON.
     *
     * @throws OAuthRefusedException When response types other than `code` are named.
     */
    private static function responseTypes(array $decoded): void
    {
        $responseTypes = self::words($decoded, 'response_types', ['code']);

        if ($responseTypes !== ['code']) {
            throw self::refused('The response_types may only be code.');
        }
    }

    /**
     * @param string       $scope              The space-separated scopes asked for; empty for all it may hold.
     * @param list<string> $registrationScopes The most a registered client may hold.
     *
     * @throws OAuthRefusedException When one is beyond what a registered client may hold.
     *
     * @return list<string> The scopes.
     */
    private static function scopes(string $scope, array $registrationScopes): array
    {
        $asked = array_values(array_unique(array_filter(explode(' ', $scope), static fn (string $word): bool => $word !== '')));

        if ($asked === []) {
            return $registrationScopes;
        }

        $beyond = array_diff($asked, $registrationScopes);

        if ($beyond !== []) {
            throw self::refused('The scopes ' . implode(' ', $beyond) . ' are not ones a registered client may hold.');
        }

        return $asked;
    }

    /**
     * @param string $description What was wrong.
     *
     * @return OAuthRefusedException The `invalid_client_metadata` refusal.
     */
    private static function refused(string $description): OAuthRefusedException
    {
        return new OAuthRefusedException('invalid_client_metadata', $description);
    }
}
