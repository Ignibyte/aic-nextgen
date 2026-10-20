<?php

declare(strict_types=1);

namespace AIC\Notification\Infrastructure;

use AIC\Notification\Contracts\MailTransport;
use AIC\Notification\Domain\MailConfig;
use Override;
use RuntimeException;

use function fclose;
use function fgets;
use function fsockopen;
use function fwrite;
use function str_starts_with;
use function stream_set_timeout;
use function strlen;
use function substr;
use function trim;

/**
 * A plain SMTP client (#48): EHLO, MAIL FROM, RCPT TO, DATA and QUIT on one
 * connection per message, over `fsockopen` (elephc's `stream_socket_client`
 * takes only an address). No STARTTLS or AUTH yet: a local relay or DDEV's
 * Mailpit.
 */
final class SmtpTransport implements MailTransport
{
    /**
     * The open connection, in a one-slot list; empty between messages.
     *
     * @var list<resource>
     */
    private array $connection = [];

    /**
     * @param MailConfig $mailConfig The server and the sender.
     */
    public function __construct(
        private readonly MailConfig $mailConfig,
    ) {
    }

    /**
     * @param string $recipient The recipient's address.
     * @param string $message   The message, as MailMessage writes it.
     *
     * @throws RuntimeException When the server cannot be reached or refuses a step.
     */
    #[Override]
    public function send(string $recipient, string $message): void
    {
        $this->open();

        try {
            $this->command('EHLO aic', '250');
            $this->command('MAIL FROM:<' . $this->mailConfig->from . '>', '250');
            $this->command('RCPT TO:<' . $recipient . '>', '25');
            $this->command('DATA', '354');
            $this->command($message . "\r\n.", '250');
            $this->command('QUIT', '221');
        } finally {
            $this->close();
        }
    }

    /**
     * @throws RuntimeException When the server cannot be reached or does not greet.
     */
    private function open(): void
    {
        $errorCode = 0;
        $errorMessage = '';
        $socket = fsockopen($this->mailConfig->host, $this->mailConfig->port, $errorCode, $errorMessage, 10.0);

        if ($socket === false) {
            $server = $this->mailConfig->host . ':' . $this->mailConfig->port;

            throw new RuntimeException('SMTP: cannot reach ' . $server . ': ' . $errorMessage);
        }

        stream_set_timeout($socket, 10);
        $this->connection = [$socket];
        $this->expect('220');
    }

    /**
     * @param string $line     One command, or the message ending in its dot.
     * @param string $expected The reply code it must start with.
     *
     * @throws RuntimeException When the reply does not.
     */
    private function command(string $line, string $expected): void
    {
        foreach ($this->connection as $socket) {
            fwrite($socket, $line . "\r\n");
        }

        $this->expect($expected);
    }

    /**
     * @param string $expected The reply code it must start with.
     *
     * @throws RuntimeException When the reply does not.
     */
    private function expect(string $expected): void
    {
        $reply = '';

        // A reply's last line has a space after its code; the others, a hyphen.
        do {
            $line = $this->line();
            $reply .= $line;
        } while (strlen($line) >= 4 && substr($line, 3, 1) === '-');

        if (!str_starts_with($reply, $expected)) {
            throw new RuntimeException('SMTP: expected ' . $expected . ', got "' . trim($reply) . '".');
        }
    }

    /**
     * @return string The next line from the server; empty at the end or on a timeout.
     */
    private function line(): string
    {
        foreach ($this->connection as $socket) {
            $line = fgets($socket);

            return $line === false
                ? ''
                : $line;
        }

        return '';
    }

    /**
     * Closes the connection.
     */
    private function close(): void
    {
        foreach ($this->connection as $socket) {
            fclose($socket);
        }

        $this->connection = [];
    }
}
