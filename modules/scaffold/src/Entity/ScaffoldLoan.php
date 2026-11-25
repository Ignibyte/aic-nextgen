<?php

declare(strict_types=1);

namespace App\Scaffold\Entity;

/**
 * One `scaffold_loans` row, made by bin/aic make:entity (#123).
 */
final readonly class ScaffoldLoan
{
    /**
     * @param string  $bookId     The book_id column.
     * @param ?int    $taskId     The task_id column.
     * @param string  $borrower   The borrower column.
     * @param string  $lentAt     The lent_at column.
     * @param ?string $returnedAt The returned_at column.
     */
    public function __construct(
        public string $bookId,
        public ?int $taskId,
        public string $borrower,
        public string $lentAt,
        public ?string $returnedAt,
    ) {
    }

    /**
     * @return string The row in one line, for its list.
     */
    public function line(): string
    {
        return 'book id: ' . $this->bookId
            . ', task id: ' . ($this->taskId ?? '')
            . ', borrower: ' . $this->borrower
            . ', lent at: ' . $this->lentAt
            . ', returned at: ' . ($this->returnedAt ?? '');
    }
}
