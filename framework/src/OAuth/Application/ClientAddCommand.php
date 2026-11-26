<?php

declare(strict_types=1);

namespace AIC\OAuth\Application;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Database\Contracts\QueryException;
use InvalidArgumentException;
use Override;

use function array_all;
use function array_filter;
use function array_map;
use function array_slice;
use function array_values;
use function implode;
use function in_array;
use function parse_url;
use function preg_match;
use function str_contains;
use function str_starts_with;
use function substr;

use const PHP_URL_HOST;
use const PHP_URL_SCHEME;

/**
 * `bin/aic client:add <id> <secret>|- <scope>... [--redirect=<uri>]...`
 * (#331): an OAuth client. A secret (32 or more visible characters, only its
 * SHA-256 kept) makes a confidential client, which may use both grants; `-`
 * makes a public one, which needs a redirect URI and may only use the
 * authorization code grant with PKCE. Each scope is a permission's name;
 * each redirect URI is https, or http on a loopback host, with no fragment.
 * Exit 0 when added, 1 when the id is taken, 2 for a line it does not take.
 */
final readonly class ClientAddCommand implements Command
{
    /**
     * A client id: lower-case words joined by `-`.
     */
    public const string ID = '/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/';

    /**
     * A secret: 32 or more visible characters.
     */
    private const string VALUE = '/^[\\x21-\\x7e]{32,}$/';

    /**
     * A scope: a permission's name.
     */
    private const string SCOPE = '/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*(?:\\.[a-z][a-z0-9]*(?:-[a-z0-9]+)*)+$/';

    /**
     * The option naming a redirect URI.
     */
    private const string REDIRECT = '--redirect=';

    /**
     * What the command takes.
     */
    private const string USAGE = 'Usage: client:add <id> <secret>|- <scope>... [--redirect=<uri>]...; an id is lower-case'
        . ' words joined by -, a secret 32 or more visible characters (- for a public client, which needs a redirect'
        . ' URI), a scope a permission (notes.read), a redirect URI https (http on a loopback host) with no fragment.';

    /**
     * The hosts plain http is allowed on.
     */
    private const array LOOPBACK = ['127.0.0.1', 'localhost', '[::1]'];

    /**
     * @param OAuthClients $oauthClients The clients.
     */
    public function __construct(
        private OAuthClients $oauthClients,
    ) {
    }

    /**
     * @param CommandInput $commandInput The id, the secret or `-`, the scopes and the redirect URIs.
     *
     * @throws InvalidArgumentException When the response exit code is outside 0 to 255.
     * @throws QueryException           When the client cannot be written.
     *
     * @return CommandResponse Whether it was added.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        $arguments = $commandInput->arguments();
        $clientId = $arguments[0] ?? '';
        $secret = $arguments[1] ?? '';
        $rest = array_slice($arguments, 2);
        $scopes = array_values(array_filter($rest, static fn (string $argument): bool => !str_starts_with($argument, self::REDIRECT)));
        $redirectUris = array_map(
            static fn (string $argument): string => substr($argument, 11),
            array_values(array_filter($rest, static fn (string $argument): bool => str_starts_with($argument, self::REDIRECT))),
        );

        if (!$this->takes($clientId, $secret, $scopes, $redirectUris)) {
            return new CommandResult([self::USAGE], 2);
        }

        $added = $this->oauthClients->add($clientId, $secret === '-' ? '' : $secret, $scopes, $redirectUris);

        return new CommandResult(
            ['client:add ' . $clientId . ' ' . implode(' ', $scopes) . ($added ? ': done.' : ': the id is taken.')],
            $added ? 0 : 1,
        );
    }

    /**
     * @param string       $clientId     The id.
     * @param string       $secret       The secret, or `-`.
     * @param list<string> $scopes       The scopes.
     * @param list<string> $redirectUris The redirect URIs.
     *
     * @return bool Whether each is well formed, there is a scope, and a public client has a redirect URI.
     */
    private function takes(string $clientId, string $secret, array $scopes, array $redirectUris): bool
    {
        return preg_match(self::ID, $clientId) === 1
            && ($secret === '-' ? $redirectUris !== [] : preg_match(self::VALUE, $secret) === 1)
            && $scopes !== []
            && array_all($scopes, static fn (string $scope): bool => preg_match(self::SCOPE, $scope) === 1)
            && array_all($redirectUris, static fn (string $uri): bool => self::redirectable($uri));
    }

    /**
     * @param string $uri A redirect URI.
     *
     * @return bool Whether it is https, or http on a loopback host, with a host and no fragment.
     */
    private static function redirectable(string $uri): bool
    {
        $scheme = parse_url($uri, PHP_URL_SCHEME);
        $host = parse_url($uri, PHP_URL_HOST);

        return !str_contains($uri, '#')
            && !str_contains($uri, ' ')
            && ($scheme === 'https' || ($scheme === 'http' && in_array($host, self::LOOPBACK, true)))
            && $host !== null
            && $host !== '';
    }
}
