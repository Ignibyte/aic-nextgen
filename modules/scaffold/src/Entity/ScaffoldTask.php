<?php

declare(strict_types=1);

namespace App\Scaffold\Entity;

/**
 * One `scaffold_tasks` row, made by bin/aic make:entity (#123).
 */
final readonly class ScaffoldTask
{
    /**
     * @param string $title The title column.
     * @param string $dueOn The due_on column.
     * @param bool   $done  The done column.
     */
    public function __construct(
        public string $title,
        public string $dueOn,
        public bool $done,
    ) {
    }

    /**
     * @return string The row in one line, for its list.
     */
    public function line(): string
    {
        return 'title: ' . $this->title
            . ', due on: ' . $this->dueOn
            . ', done: ' . ($this->done ? 'yes' : 'no');
    }
}
