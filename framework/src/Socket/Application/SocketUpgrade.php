<?php

declare(strict_types=1);

namespace AIC\Socket\Application;

use AIC\Database\Contracts\QueryException;
use AIC\Socket\Domain\Handshake;
use AIC\Socket\Domain\SocketEntry;
use AIC\Socket\Domain\UpgradeParser;

use function strlen;
use function strpos;
use function substr;

/**
 * A new connection's opening handshake (#32): once its request has arrived
 * whole, it is admitted with a 101 and opened on its socket, or answered with
 * its refusal and dropped. A request that grows past 8 KiB without its end is
 * refused 400.
 */
final readonly class SocketUpgrade
{
    /**
     * The longest upgrade request read.
     */
    private const int MAX_HEAD = 8_192;

    /**
     * @param SocketAdmission $socketAdmission Decides who may open one.
     * @param Handshake       $handshake       Writes the answers.
     * @param UpgradeParser   $upgradeParser   Reads the request.
     */
    public function __construct(
        private SocketAdmission $socketAdmission,
        private Handshake $handshake = new Handshake(),
        private UpgradeParser $upgradeParser = new UpgradeParser(),
    ) {
    }

    /**
     * @param int                        $id          A connection not yet opened.
     * @param Connections                $connections The connections.
     * @param array<string, SocketEntry> $entries     Every declared socket by path.
     *
     * @throws QueryException When the session or the roles cannot be read.
     */
    public function open(int $id, Connections $connections, array $entries): void
    {
        $buffer = $connections->buffer($id);
        $end = strpos($buffer, "\r\n\r\n");

        if ($end === false) {
            $this->refuseLong($id, $connections, strlen($buffer));

            return;
        }

        $connections->consume($id, $end + 4);
        $upgradeHead = $this->upgradeParser->read(substr($buffer, 0, $end));
        $admission = $this->socketAdmission->admit($upgradeHead, $entries);

        if ($admission->status !== 101) {
            $connections->write($id, $this->handshake->refused($admission->status, $admission->reason));
            $connections->drop($id);

            return;
        }

        $connections->write($id, $this->handshake->accepted($upgradeHead));
        $connections->open($id, $upgradeHead->path, $admission->user);
    }

    /**
     * @param int         $id          A connection still sending its request.
     * @param Connections $connections The connections.
     * @param int         $length      How much of it has arrived.
     */
    private function refuseLong(int $id, Connections $connections, int $length): void
    {
        if ($length <= self::MAX_HEAD) {
            return;
        }

        $connections->write($id, $this->handshake->refused(400, 'Bad Request'));
        $connections->drop($id);
    }
}
