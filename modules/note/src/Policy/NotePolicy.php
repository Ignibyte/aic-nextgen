<?php

declare(strict_types=1);

namespace App\Note\Policy;

use AIC\Access\Contracts\Policy;
use AIC\Access\Domain\Actor;
use AIC\Access\Domain\Decision;
use App\Note\Entity\Note;

use function str_starts_with;

/**
 * What an actor may do with one Note, made by bin/aic make:policy (#304):
 * a pinned note, one whose title starts with `Pinned:`, is deleted only by
 * an actor holding `notes.delete-pinned`; any other note by anyone.
 */
final readonly class NotePolicy implements Policy
{
    /**
     * @param Actor $actor Who asks.
     * @param Note  $note  The record.
     *
     * @return Decision Allow unless the note is pinned and the actor lacks `notes.delete-pinned`.
     */
    public function delete(Actor $actor, Note $note): Decision
    {
        return !str_starts_with($note->title, 'Pinned:') || $actor->can('notes.delete-pinned')
            ? Decision::Allow
            : Decision::Deny;
    }
}
