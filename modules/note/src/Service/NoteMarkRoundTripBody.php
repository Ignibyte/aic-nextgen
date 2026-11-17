<?php

declare(strict_types=1);

namespace App\Note\Service;

use JsonSerializable;
use Override;

/**
 * GET /note-marks/round-trip's body (#307): whether the key was a uuid, the
 * mark as read back, its kind after the change, whether the delete took, and
 * whether a kind off the list was refused.
 */
final readonly class NoteMarkRoundTripBody implements JsonSerializable
{
    /**
     * @param bool                  $keyed   Whether the new key is a lower-case uuid.
     * @param array<string, string> $read    The mark's fields as read back.
     * @param string                $changed Its kind after the change.
     * @param bool                  $deleted Whether it is gone after the delete.
     * @param bool                  $refused Whether a kind off the list was refused.
     */
    public function __construct(
        private bool $keyed,
        private array $read,
        private string $changed,
        private bool $deleted,
        private bool $refused,
    ) {
    }

    /**
     * @return array<string, bool|string|array<string, string>> The JSON object.
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'changed' => $this->changed,
            'deleted' => $this->deleted,
            'keyed' => $this->keyed,
            'read' => $this->read,
            'refused' => $this->refused,
        ];
    }
}
