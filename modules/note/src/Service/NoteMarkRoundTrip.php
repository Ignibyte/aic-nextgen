<?php

declare(strict_types=1);

namespace App\Note\Service;

use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityNotFoundException;
use App\Note\Entity\NoteMark;
use App\Note\Repository\NoteMarkRepository;
use InvalidArgumentException;

use function preg_match;

/**
 * GET /note-marks/round-trip's work (#307): one uuid-keyed mark with a field
 * of every type written, read back, changed, deleted, and a value off its
 * choice list refused, so gate:23 compares each step on PHP and native. It
 * leaves the table as it found it.
 */
final readonly class NoteMarkRoundTrip
{
    /** The mark written, each value in its type's form. */
    private const array MARK = [
        'kind' => 'pin',
        'label' => 'round trip',
        'marked_at' => '2026-11-17T09:30:00+01:00',
        'meta' => '{"pinned":true,"tags":["a","b"]}',
        'origin' => '1b4e28ba-2fa1-11d2-883f-0016d3cca427',
        'weight' => '12.50',
    ];

    /**
     * @param NoteMarkRepository $noteMarkRepository The marks.
     */
    public function __construct(
        private NoteMarkRepository $noteMarkRepository,
    ) {
    }

    /**
     * @throws InvalidArgumentException When a value does not fit its field.
     * @throws EntityNotFoundException  When the mark is gone before it is changed.
     * @throws QueryException           When a query fails.
     *
     * @return NoteMarkRoundTripBody What each step saw.
     */
    public function body(): NoteMarkRoundTripBody
    {
        $key = $this->noteMarkRepository->add(self::MARK);
        $written = $this->noteMarkRepository->find($key) ?? throw new EntityNotFoundException('The mark just written is gone.');
        $this->noteMarkRepository->rekind($key, 'flag');
        $changed = $this->noteMarkRepository->find($key);
        $this->noteMarkRepository->remove($key);

        return new NoteMarkRoundTripBody(
            preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $key) === 1,
            self::seen($written),
            $changed instanceof NoteMark ? $changed->kind : '',
            !$this->noteMarkRepository->find($key) instanceof NoteMark,
            $this->refused(),
        );
    }

    /**
     * @throws QueryException When the insert fails for any other reason.
     *
     * @return bool Whether a kind off the list is refused before the database sees it.
     */
    private function refused(): bool
    {
        try {
            $offList = self::MARK;
            $offList['kind'] = 'nope';
            $this->noteMarkRepository->add($offList);
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    }

    /**
     * @param NoteMark $noteMark The mark as read back.
     *
     * @return array<string, string> Its fields as read: the JSON as Postgres keeps it, the time in the database's zone.
     */
    private static function seen(NoteMark $noteMark): array
    {
        return [
            'kind' => $noteMark->kind,
            'label' => $noteMark->label,
            'marked_at' => $noteMark->markedAt->format('Y-m-d\\TH:i:sP'),
            'meta' => $noteMark->meta,
            'origin' => $noteMark->origin,
            'weight' => $noteMark->weight,
        ];
    }
}
