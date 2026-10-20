<?php

declare(strict_types=1);

namespace AIC\Notification\Domain;

use InvalidArgumentException;

use function base64_encode;
use function gmdate;
use function preg_match;
use function preg_replace;
use function str_replace;

/**
 * One plain-text mail as SMTP's DATA carries it (#48): the headers, a blank
 * line, the body with CRLF line ends and leading dots doubled. A subject that
 * is not plain ASCII is encoded; an address or subject holding a line break
 * is refused, so no header can be injected.
 */
final readonly class MailMessage
{
    /**
     * @param string $from      The sender's address.
     * @param string $recipient The recipient's address.
     * @param string $subject   One line.
     * @param string $body      The text.
     * @param int    $timestamp When it is sent, for the Date header.
     *
     * @throws InvalidArgumentException When an address or the subject holds a line break, or an address is not one.
     */
    public function __construct(
        private string $from,
        private string $recipient,
        private string $subject,
        private string $body,
        private int $timestamp,
    ) {
        foreach ([$from, $recipient] as $address) {
            if (preg_match('/^[^\\s<>@]+@[^\\s<>@]+$/', $address) !== 1) {
                throw new InvalidArgumentException('Not a mail address: "' . $address . '".');
            }
        }

        if (preg_match('/[\\r\\n]/', $subject) === 1) {
            throw new InvalidArgumentException('A mail subject is one line.');
        }
    }

    /**
     * @return string The message, ready for DATA without its final dot.
     */
    public function text(): string
    {
        $body = (string) preg_replace('/^\\./m', '..', str_replace("\n", "\r\n", str_replace("\r\n", "\n", $this->body)));

        return 'From: ' . $this->from . "\r\n" . 'To: ' . $this->recipient . "\r\n" . 'Subject: ' . $this->encodedSubject() . "\r\n"
            . 'Date: ' . gmdate('D, d M Y H:i:s', $this->timestamp) . " +0000\r\n"
            . "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n"
            . $body;
    }

    /**
     * @return string The subject as is when it is printable ASCII; else base64 encoded-word UTF-8.
     */
    private function encodedSubject(): string
    {
        return preg_match('/^[\\x20-\\x7e]*$/', $this->subject) === 1
            ? $this->subject
            : '=?UTF-8?B?' . base64_encode($this->subject) . '?=';
    }
}
