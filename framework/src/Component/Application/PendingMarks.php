<?php

declare(strict_types=1);

namespace AIC\Component\Application;

use AIC\Component\Domain\ChildFrame;

/**
 * The Tessera marks a render pass has set for the child it is rendering and
 * that child's root has not printed yet (#189). One pass holds one, so a
 * nested child sets its own after its parent's root took the parent's.
 */
final class PendingMarks
{
    /**
     * The child's key and props token; null when none is pending.
     */
    private ?ChildFrame $childFrame = null;

    /**
     * @param ChildFrame $childFrame The marks the next component root prints.
     */
    public function set(ChildFrame $childFrame): void
    {
        $this->childFrame = $childFrame;
    }

    /**
     * @return ChildFrame|null The pending marks, now taken; null when none was pending.
     */
    public function take(): ?ChildFrame
    {
        $childFrame = $this->childFrame;
        $this->childFrame = null;

        return $childFrame;
    }
}
