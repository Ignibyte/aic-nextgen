<?php

declare(strict_types=1);

namespace AIC\Socket\Application;

use AIC\Access\Application\PostgresRoles;
use AIC\Access\Domain\Actor;
use AIC\Auth\Application\Authenticator;
use AIC\Database\Contracts\QueryException;
use AIC\Session\Application\SessionMiddleware;
use AIC\Session\Contracts\SessionStore;
use AIC\Session\Domain\StringMapJson;
use AIC\Socket\Domain\Admission;
use AIC\Socket\Domain\Handshake;
use AIC\Socket\Domain\SocketEntry;
use AIC\Socket\Domain\UpgradeHead;

use function strtolower;

/**
 * Who may open a connection (#32): a version-13 upgrade to a declared socket
 * whose `Origin` names the host it was sent to, so another site's page cannot
 * open one with a visitor's cookie, by the user the session cookie holds when
 * the socket's `needs:` holds for them. The session is read once, here; a
 * bearer token is not read, as a browser cannot send one on an upgrade.
 */
final readonly class SocketAdmission
{
    /**
     * @param SessionStore  $sessionStore  The sessions.
     * @param PostgresRoles $postgresRoles A signed-in user's permissions.
     * @param StringMapJson $stringMapJson Reads a session's payload.
     * @param Handshake     $handshake     Reads the upgrade.
     */
    public function __construct(
        private SessionStore $sessionStore,
        private PostgresRoles $postgresRoles,
        private StringMapJson $stringMapJson = new StringMapJson(),
        private Handshake $handshake = new Handshake(),
    ) {
    }

    /**
     * @param UpgradeHead                $upgradeHead The upgrade request.
     * @param array<string, SocketEntry> $entries     Every declared socket by path.
     *
     * @throws QueryException When the session or the roles cannot be read.
     *
     * @return Admission 101 for the user, or the refusal.
     */
    public function admit(UpgradeHead $upgradeHead, array $entries): Admission
    {
        $entry = $entries[$upgradeHead->path] ?? null;

        if (!$this->handshake->asked($upgradeHead)) {
            return new Admission(400, 'Bad Request');
        }

        if (!$entry instanceof SocketEntry) {
            return new Admission(404, 'Not Found');
        }

        $user = $this->user($upgradeHead);

        return $this->sameOrigin($upgradeHead) && $this->allowed($entry->needs, $user)
            ? new Admission(101, 'Switching Protocols', $user)
            : new Admission(403, 'Forbidden');
    }

    /**
     * @param UpgradeHead $upgradeHead The upgrade request.
     *
     * @return bool Whether its `Origin` is its `Host` over http or https.
     */
    private function sameOrigin(UpgradeHead $upgradeHead): bool
    {
        $origin = strtolower($upgradeHead->header('origin'));
        $host = strtolower($upgradeHead->header('host'));

        return $host !== '' && ($origin === 'https://' . $host || $origin === 'http://' . $host);
    }

    /**
     * @param UpgradeHead $upgradeHead The upgrade request.
     *
     * @throws QueryException When the session cannot be read.
     *
     * @return string The user its session cookie's session holds; empty for none.
     */
    private function user(UpgradeHead $upgradeHead): string
    {
        $id = $upgradeHead->cookie(SessionMiddleware::COOKIE);
        $payload = $id === ''
            ? null
            : $this->sessionStore->read($id);

        return $payload === null
            ? ''
            : $this->stringMapJson->decode($payload)[Authenticator::USER] ?? '';
    }

    /**
     * @param string $needs A permission or `signed-in`; empty for anyone.
     * @param string $user  The user's id; empty for a visitor.
     *
     * @throws QueryException When the roles cannot be read.
     *
     * @return bool Whether the user may open the socket.
     */
    private function allowed(string $needs, string $user): bool
    {
        if ($needs === '') {
            return true;
        }

        $permissions = $user === ''
            ? []
            : $this->postgresRoles->permissions($user);

        return (new Actor($user, $permissions))->can($needs);
    }
}
