<?php

declare(strict_types=1);

namespace App\Ledger\Service;

use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityStore;
use App\Ledger\Repository\LedgerRepository;
use Closure;
use Throwable;

/**
 * GET /ledger/guards' work (#310): each step writes in a transaction and ends
 * with a write the database refuses, so the step rolls back and the
 * append-only table keeps nothing. The trail shows what each guard said, by
 * SQLSTATE: 23001 an append-only or final-once guard, 23514 a check, 23505 a
 * unique partial index, 428C9 a write to a generated column.
 */
final readonly class LedgerGuards
{
    /**
     * @param EntityStore      $entityStore      Runs each step in a transaction.
     * @param LedgerRepository $ledgerRepository Writes and reads the entries and reviews.
     */
    public function __construct(
        private EntityStore $entityStore,
        private LedgerRepository $ledgerRepository,
    ) {
    }

    /**
     * @throws Throwable When a step fails for a reason other than a refused write.
     *
     * @return LedgerLines Each step and what came of it.
     */
    public function body(): LedgerLines
    {
        $ledgerTrail = new LedgerTrail();
        $this->attempt($ledgerTrail, 'change an entry', $this->generated($ledgerTrail));
        $this->attempt($ledgerTrail, 'delete an entry', $this->deleted());
        $this->attempt($ledgerTrail, 'write a total', $this->written());
        $this->attempt($ledgerTrail, 'enter no crates', $this->unchecked());
        $this->attempt($ledgerTrail, 'change a decision', $this->decided($ledgerTrail));
        $this->attempt($ledgerTrail, 'open a second review', $this->reopened());
        $this->attempt($ledgerTrail, 'delete a decided review', $this->withdrawn());
        $this->attempt($ledgerTrail, 'decide unsigned', $this->unsigned());

        return new LedgerLines('steps', $ledgerTrail->lines());
    }

    /**
     * @param LedgerTrail     $ledgerTrail The trail.
     * @param string          $step        The step's name.
     * @param Closure(): void $work        Its writes.
     *
     * @throws Throwable When the work fails for a reason other than a refused write.
     */
    private function attempt(LedgerTrail $ledgerTrail, string $step, Closure $work): void
    {
        try {
            $this->entityStore->transaction($work);
            $ledgerTrail->note($step, 'allowed');
        } catch (QueryException $queryException) {
            $ledgerTrail->note($step, $queryException->getMessage());
        }
    }

    /**
     * @param LedgerTrail $ledgerTrail The trail, for the generated columns.
     *
     * @return Closure(): void Writes an entry, notes its generated total and label, then changes it.
     */
    private function generated(LedgerTrail $ledgerTrail): Closure
    {
        $ledgerRepository = $this->ledgerRepository;

        return static function () use ($ledgerRepository, $ledgerTrail): void {
            $entry = $ledgerRepository->entry([]);
            $row = $ledgerRepository->read($entry);
            $ledgerTrail->note('total', $row->string('total'));
            $ledgerTrail->note('label', $row->string('label'));

            $ledgerRepository->change($entry, ['quantity' => 4]);
        };
    }

    /**
     * @return Closure(): void Writes an entry, then deletes it.
     */
    private function deleted(): Closure
    {
        $ledgerRepository = $this->ledgerRepository;

        return static function () use ($ledgerRepository): void {
            $ledgerRepository->remove($ledgerRepository->entry([]));
        };
    }

    /**
     * @return Closure(): void Writes an entry naming its own total.
     */
    private function written(): Closure
    {
        $ledgerRepository = $this->ledgerRepository;

        return static function () use ($ledgerRepository): void {
            $ledgerRepository->entry(['total' => '1.00']);
        };
    }

    /**
     * @return Closure(): void Writes an entry of no crates.
     */
    private function unchecked(): Closure
    {
        $ledgerRepository = $this->ledgerRepository;

        return static function () use ($ledgerRepository): void {
            $ledgerRepository->entry(['quantity' => 0]);
        };
    }

    /**
     * @param LedgerTrail $ledgerTrail The trail, for the first decision.
     *
     * @return Closure(): void Opens a review, decides it, then changes the decision.
     */
    private function decided(LedgerTrail $ledgerTrail): Closure
    {
        $ledgerRepository = $this->ledgerRepository;

        return static function () use ($ledgerRepository, $ledgerTrail): void {
            $review = $ledgerRepository->review($ledgerRepository->entry([]), []);
            $ledgerRepository->decide($review, ['decision' => 'approved', 'reviewer' => 'Ada']);
            $ledgerTrail->note('decide an open review', 'allowed');
            $ledgerRepository->decide($review, ['decision' => 'rejected']);
        };
    }

    /**
     * @return Closure(): void Opens two reviews of one entry.
     */
    private function reopened(): Closure
    {
        $ledgerRepository = $this->ledgerRepository;

        return static function () use ($ledgerRepository): void {
            $entry = $ledgerRepository->entry([]);
            $ledgerRepository->review($entry, []);
            $ledgerRepository->review($entry, []);
        };
    }

    /**
     * @return Closure(): void Writes a decided review, then deletes it.
     */
    private function withdrawn(): Closure
    {
        $ledgerRepository = $this->ledgerRepository;

        return static function () use ($ledgerRepository): void {
            $entry = $ledgerRepository->entry([]);
            $ledgerRepository->withdraw($ledgerRepository->review($entry, ['decision' => 'approved', 'reviewer' => 'Grace']));
        };
    }

    /**
     * @return Closure(): void Writes a decision with no reviewer.
     */
    private function unsigned(): Closure
    {
        $ledgerRepository = $this->ledgerRepository;

        return static function () use ($ledgerRepository): void {
            $ledgerRepository->review($ledgerRepository->entry([]), ['decision' => 'rejected']);
        };
    }
}
