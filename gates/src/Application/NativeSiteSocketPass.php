<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\CompilerOutput;
use Gates\Domain\NativeSiteSetup;
use Gates\Domain\SocketConversation;
use Gates\Domain\SocketPlayer;
use InvalidArgumentException;
use RuntimeException;

use function array_any;
use function count;
use function implode;
use function sprintf;
use function str_contains;
use function usleep;

use const PHP_EOL;

/**
 * gate:23's socket pass (#32): the compiled socket server (`bootstrap/socket.php` over the site's bundle) beside
 * `bin/aic socket:serve`, each conversation of the script's `sockets` played on PHP's and then on the compiled one,
 * and their transcripts compared. A conversation's upgrade must answer the status it names on PHP, and every socket
 * the surface declares needs a conversation that opens it.
 */
final readonly class NativeSiteSocketPass
{
    /**
     * Microseconds between the two passes: two of the server's ticks, so a broadcast the first pass published is
     * drained before the second connects.
     */
    private const int PAUSE = 600_000;

    /**
     * The status of an accepted upgrade.
     */
    private const int SWITCHING = 101;

    /**
     * @param NativeSiteBuilder  $nativeSiteBuilder  Compiles the socket server.
     * @param NativeSiteSites    $nativeSiteSites    Starts both socket servers.
     * @param SocketPlayer       $socketPlayer       Plays a conversation.
     * @param NativeSocketScript $nativeSocketScript Reads the conversations.
     * @param NativeSiteRoutes   $nativeSiteRoutes   Reads the declared sockets.
     */
    public function __construct(
        private NativeSiteBuilder $nativeSiteBuilder,
        private NativeSiteSites $nativeSiteSites,
        private SocketPlayer $socketPlayer,
        private NativeSocketScript $nativeSocketScript = new NativeSocketScript(),
        private NativeSiteRoutes $nativeSiteRoutes = new NativeSiteRoutes(),
    ) {
    }

    /**
     * @param string          $root            The app's root.
     * @param string          $workDir         The native site's work directory, its bundle written.
     * @param NativeSiteSetup $nativeSiteSetup The ports and environment.
     *
     * @return array{int, list<string>, list<string>} The conversations played, their differences and the other
     *                                                findings; nothing when the script names none and no socket is
     *                                                declared.
     */
    public function run(string $root, string $workDir, NativeSiteSetup $nativeSiteSetup): array
    {
        try {
            $conversations = $this->nativeSocketScript->conversations($root);
            $unopened = self::unopened($this->nativeSiteRoutes->sockets($root), $conversations);
        } catch (InvalidArgumentException $invalidArgumentException) {
            return [0, [], ['SOCKETS ' . $invalidArgumentException->getMessage()]];
        }

        return $conversations === []
            ? [0, [], $unopened]
            : $this->played($root, $workDir, $nativeSiteSetup, [$conversations, $unopened]);
    }

    /**
     * @param string                                        $root            The app's root.
     * @param string                                        $workDir         The native site's work directory.
     * @param NativeSiteSetup                               $nativeSiteSetup The ports.
     * @param array{list<SocketConversation>, list<string>} $script          The conversations, then the declared
     *                                                                       sockets none opens.
     *
     * @return array{int, list<string>, list<string>} What {@see run()} answers.
     */
    private function played(string $root, string $workDir, NativeSiteSetup $nativeSiteSetup, array $script): array
    {
        [$conversations, $unopened] = $script;
        $problem = $this->started($root, $workDir, $nativeSiteSetup);

        if ($problem !== '') {
            return [0, [], [...$unopened, $problem]];
        }

        $php = $this->transcripts($conversations, $nativeSiteSetup->socketPorts[0]);
        usleep(self::PAUSE);
        $native = $this->transcripts($conversations, $nativeSiteSetup->socketPorts[1]);

        return [
            count($conversations),
            self::differences($conversations, $php, $native),
            [...$unopened, ...self::refused($conversations, $php)],
        ];
    }

    /**
     * @param string          $root            The app's root.
     * @param string          $workDir         The native site's work directory.
     * @param NativeSiteSetup $nativeSiteSetup The ports.
     *
     * @return string Why the servers are not up: the compiler's report, or a server that did not start; empty when
     *                both answer.
     */
    private function started(string $root, string $workDir, NativeSiteSetup $nativeSiteSetup): string
    {
        try {
            $processResult = $this->nativeSiteBuilder->buildSocket($root, $workDir . '/socket');
        } catch (RuntimeException $runtimeException) {
            return 'SOCKETS ' . $runtimeException->getMessage();
        }

        if ($processResult->exitCode !== 0) {
            $compilerOutput = new CompilerOutput($processResult->stdout, $processResult->stderr);

            return 'SOCKETS the socket server does not compile natively:' . PHP_EOL . $compilerOutput->report();
        }

        return $this->nativeSiteSites->startSockets($root, $workDir, $nativeSiteSetup)
            ? ''
            : 'SOCKETS a socket server did not start; see ' . $workDir . '/php-socket.log and native-socket.log.';
    }

    /**
     * @param list<SocketConversation> $conversations The conversations.
     * @param int                      $port          A socket server's port.
     *
     * @return list<list<string>> Each conversation's transcript on it.
     */
    private function transcripts(array $conversations, int $port): array
    {
        $transcripts = [];

        foreach ($conversations as $conversation) {
            $transcripts[] = $this->socketPlayer->play($port, $conversation);
        }

        return $transcripts;
    }

    /**
     * @param list<SocketConversation> $conversations The conversations.
     * @param list<list<string>>       $php           PHP's transcripts.
     * @param list<list<string>>       $native        The compiled server's.
     *
     * @return list<string> One line per conversation whose transcripts differ.
     */
    private static function differences(array $conversations, array $php, array $native): array
    {
        $lines = [];

        foreach ($conversations as $index => $conversation) {
            $ours = $php[$index] ?? [];
            $theirs = $native[$index] ?? [];

            if ($ours === $theirs) {
                continue;
            }

            $lines[] = sprintf(
                'SOCKET %s (%s): PHP %s; native %s',
                $conversation->name,
                $conversation->path,
                implode(' | ', $ours),
                implode(' | ', $theirs),
            );
        }

        return $lines;
    }

    /**
     * @param list<SocketConversation> $conversations The conversations.
     * @param list<list<string>>       $php           PHP's transcripts.
     *
     * @return list<string> One line per conversation whose upgrade PHP answered with another status than it names.
     */
    private static function refused(array $conversations, array $php): array
    {
        $lines = [];

        foreach ($conversations as $index => $conversation) {
            $transcript = $php[$index] ?? [];
            $status = $transcript === []
                ? ''
                : $transcript[0];

            if (str_contains($status, ' ' . $conversation->status . ' ')) {
                continue;
            }

            $lines[] = sprintf(
                'SOCKET %s (%s): PHP answered "%s", the script expects %d',
                $conversation->name,
                $conversation->path,
                $status,
                $conversation->status,
            );
        }

        return $lines;
    }

    /**
     * @param list<string>             $paths         Every declared socket's path.
     * @param list<SocketConversation> $conversations The conversations.
     *
     * @return list<string> One line per declared socket no conversation opens (status 101).
     */
    private static function unopened(array $paths, array $conversations): array
    {
        $lines = [];

        foreach ($paths as $path) {
            $opens = static fn (SocketConversation $socketConversation): bool => self::opens($socketConversation, $path);

            if (array_any($conversations, $opens)) {
                continue;
            }

            $lines[] = sprintf('UNOPENED socket %s: add a conversation that opens it to %s', $path, NativeSiteScript::PATH);
        }

        return $lines;
    }

    /**
     * @param SocketConversation $socketConversation A conversation.
     * @param string             $path               A declared socket's path.
     *
     * @return bool Whether it opens that socket: its path, and a 101 named.
     */
    private static function opens(SocketConversation $socketConversation, string $path): bool
    {
        return $socketConversation->path === $path && $socketConversation->status === self::SWITCHING;
    }
}
