<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * The ticket a run works (#100): its id, number, title, status and file.
 */
final readonly class Ticket
{
    /**
     * @param string       $id     Its id as runs and events name it: the number as text (`100`).
     * @param int          $number Its number.
     * @param string       $title  Its title.
     * @param TicketStatus $status Its status when last read or written.
     * @param string       $path   Its file, relative to the project.
     */
    public function __construct(
        public string $id,
        public int $number,
        public string $title,
        public TicketStatus $status,
        public string $path,
    ) {
    }

    /**
     * @param TicketStatus $ticketStatus The status the engine wrote.
     *
     * @return self The ticket with that status.
     */
    public function withStatus(TicketStatus $ticketStatus): self
    {
        return new self($this->id, $this->number, $this->title, $ticketStatus, $this->path);
    }

    /**
     * @return array{id: string, number: int, title: string, path: string, status: string} Its `run.json` form.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'path' => $this->path,
            'status' => $this->status->value,
            'title' => $this->title,
        ];
    }
}
