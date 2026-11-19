<?php

declare(strict_types=1);

namespace AIC\Access\Application;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Database\Contracts\QueryException;
use InvalidArgumentException;
use Override;

use function array_all;
use function array_slice;
use function implode;
use function preg_match;

/**
 * `bin/aic token:add <name> <token> <scope>...` (#313): a bearer token for
 * a server-to-server caller, as `user:add` takes a password. The operator
 * chooses the value (32 characters or more, no space) and only its SHA-256
 * is kept; each scope is a permission's name. Exit 0 when added, 1 when the
 * name or the value is taken, 2 for a line it does not take.
 */
final readonly class TokenAddCommand implements Command
{
    /**
     * A token's or a role's name: lower-case words joined by `-`.
     */
    public const string NAME = '/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/';

    /**
     * A token's value: 32 or more visible characters.
     */
    private const string VALUE = '/^[\\x21-\\x7e]{32,}$/';

    /**
     * A scope: a permission's name.
     */
    private const string SCOPE = '/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*(?:\\.[a-z][a-z0-9]*(?:-[a-z0-9]+)*)+$/';

    /**
     * @param BearerTokens $bearerTokens The tokens.
     */
    public function __construct(
        private BearerTokens $bearerTokens,
    ) {
    }

    /**
     * @param CommandInput $commandInput The name, the value and the scopes.
     *
     * @throws InvalidArgumentException When the response exit code is outside 0 to 255.
     * @throws QueryException           When the token cannot be written.
     *
     * @return CommandResponse Whether it was added.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        $arguments = $commandInput->arguments();
        $name = $arguments[0] ?? '';
        $scopes = array_slice($arguments, 2);

        if (!$this->takes($name, $arguments[1] ?? '', $scopes)) {
            return new CommandResult([
                'Usage: token:add <name> <token> <scope>...; a name is lower-case words joined by -, a token 32 or more'
                    . ' visible characters, a scope a permission (notes.read).',
            ], 2);
        }

        $added = $this->bearerTokens->add($name, $arguments[1] ?? '', $scopes);

        return new CommandResult(
            ['token:add ' . $name . ' ' . implode(' ', $scopes) . ($added ? ': done.' : ': the name or the token is taken.')],
            $added ? 0 : 1,
        );
    }

    /**
     * @param string       $name   The token's name.
     * @param string       $token  Its value.
     * @param list<string> $scopes Its scopes.
     *
     * @return bool Whether each is well formed and there is at least one scope.
     */
    private function takes(string $name, string $token, array $scopes): bool
    {
        return preg_match(self::NAME, $name) === 1
            && preg_match(self::VALUE, $token) === 1
            && $scopes !== []
            && array_all($scopes, static fn (string $scope): bool => preg_match(self::SCOPE, $scope) === 1);
    }
}
