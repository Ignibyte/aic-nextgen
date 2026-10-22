<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\TicketSource;
use AIC\Workflow\Application\UtcTime;
use AIC\Workflow\Domain\Ticket;
use AIC\Workflow\Domain\TicketStatus;
use AIC\Workflow\Domain\TicketStatuses;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;

use function array_slice;
use function count;
use function explode;
use function file_get_contents;
use function fileperms;
use function glob;
use function implode;
use function is_link;
use function is_string;
use function json_decode;
use function json_encode;
use function sprintf;
use function str_starts_with;
use function strlen;
use function substr;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * AIC's ticket files (#100): flat `NNNN-slug.md`, frontmatter between `---`
 * lines with JSON-valued strings. A move rewrites `status:`, `phase:` and
 * `updated:` as `bin/ticket.sh update` does, a missing key inserted before
 * the closing `---`, and changes no other byte.
 */
final readonly class MarkdownTickets implements TicketSource
{
    /**
     * @param string     $root       The project's root.
     * @param string     $directory  The ticket directory, relative to it.
     * @param UtcTime    $utcTime    The clock, for `updated:`.
     * @param AtomicFile $atomicFile Writes a ticket.
     */
    public function __construct(
        private string $root,
        private string $directory,
        private UtcTime $utcTime,
        private AtomicFile $atomicFile = new AtomicFile(),
    ) {
    }

    /**
     * @param string $id A ticket's number as text.
     *
     * @throws WorkflowRefusal When there is not exactly one file for it, or it is not a ticket's shape.
     *
     * @return Ticket The ticket.
     */
    #[Override]
    public function get(string $id): Ticket
    {
        $path = $this->file($id);
        $fields = $this->fields($this->lines($path));
        $status = (new TicketStatuses())->named($fields['status'] ?? '');

        if (!$status instanceof TicketStatus || ($fields['number'] ?? '') !== $id || ($fields['title'] ?? '') === '') {
            throw new WorkflowRefusal($this->relative($path) . ' is not a ticket: it needs a number, a title and a known status.');
        }

        return new Ticket($id, (int) $id, $fields['title'], $status, $this->relative($path));
    }

    /**
     * @param Ticket       $ticket       The ticket.
     * @param TicketStatus $ticketStatus Its status from now.
     * @param string       $phase        Its phase line from now.
     *
     * @throws WorkflowRefusal When the file is a symlink or cannot be written.
     *
     * @return Ticket The ticket with its new status.
     */
    #[Override]
    public function move(Ticket $ticket, TicketStatus $ticketStatus, string $phase): Ticket
    {
        $path = $this->root . '/' . $ticket->path;
        $lines = $this->lines($path);
        $lines = $this->set($lines, 'status', $ticketStatus->value);
        $lines = $this->set($lines, 'phase', json_encode($phase, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $lines = $this->set($lines, 'updated', $this->utcTime->now());

        $mode = fileperms($path);
        $this->atomicFile->write($path, implode("\n", $lines), $mode === false ? 0o644 : $mode & 0o777);

        return $ticket->withStatus($ticketStatus);
    }

    /**
     * @param string $id A ticket's number as text.
     *
     * @throws WorkflowRefusal When not exactly one file has its number.
     *
     * @return string Its file.
     */
    private function file(string $id): string
    {
        $files = glob($this->root . '/' . $this->directory . '/' . sprintf('%04d', (int) $id) . '-*.md');

        if ($files === false || count($files) !== 1) {
            throw new WorkflowRefusal('ticket ' . $id . ' is not exactly one file in ' . $this->directory . '/.');
        }

        return $files[0];
    }

    /**
     * @param string $path A ticket file.
     *
     * @throws WorkflowRefusal When it is a symlink, unreadable or has no frontmatter.
     *
     * @return list<string> Its lines, without their line breaks.
     */
    private function lines(string $path): array
    {
        $text = is_link($path)
            ? false
            : file_get_contents($path);

        if (!is_string($text) || !str_starts_with($text, "---\n")) {
            throw new WorkflowRefusal($this->relative($path) . ' is not a ticket file the workflow can read.');
        }

        return explode("\n", $text);
    }

    /**
     * @param list<string> $lines A ticket's lines.
     *
     * @return array<string, string> Its frontmatter's values by key, JSON strings decoded.
     */
    private function fields(array $lines): array
    {
        $fields = [];

        foreach (array_slice($lines, 1) as $line) {
            if ($line === '---') {
                break;
            }

            [$key, $raw] = [...explode(': ', $line, 2), ''];
            $fields[$key] = self::decoded($raw);
        }

        return $fields;
    }

    /**
     * @param list<string> $lines A ticket's lines.
     * @param string       $key   A frontmatter key.
     * @param string       $value Its raw value.
     *
     * @return list<string> The lines with the key's line replaced, or inserted before the closing `---`.
     */
    private function set(array $lines, string $key, string $value): array
    {
        for ($index = 1; $index < count($lines); $index += 1) {
            if ($lines[$index] === '---') {
                return [...array_slice($lines, 0, $index), $key . ': ' . $value, ...array_slice($lines, $index)];
            }

            if (str_starts_with($lines[$index], $key . ': ')) {
                $lines[$index] = $key . ': ' . $value;

                return $lines;
            }
        }

        return $lines;
    }

    /**
     * @param string $path A file under the root.
     *
     * @return string It, relative to the root.
     */
    private function relative(string $path): string
    {
        return str_starts_with($path, $this->root . '/')
            ? substr($path, strlen($this->root) + 1)
            : $path;
    }

    /**
     * @param string $raw A frontmatter value as written.
     *
     * @return string It, a JSON string decoded; as it is otherwise.
     */
    private static function decoded(string $raw): string
    {
        $decoded = str_starts_with($raw, '"')
            ? json_decode($raw)
            : $raw;

        return is_string($decoded)
            ? $decoded
            : $raw;
    }
}
