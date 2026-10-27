<?php

declare(strict_types=1);

namespace AIC\Component\Application;

use AIC\Component\Domain\ChildFrame;
use AIC\Component\Exception\DuplicateAnchor;

use function in_array;

/**
 * The Tessera marks a render pass has set for the child it is rendering and
 * that child's root has not printed yet (#189). One pass holds one, so a
 * nested child sets its own after its parent's root took the parent's. It
 * also keeps the anchors the pass has marked, since a page holds each once
 * (#188).
 */
final class PendingMarks
{
    /**
     * The child's key and props token; null when none is pending.
     */
    private ?ChildFrame $childFrame = null;

    /**
     * The anchors marked so far on this pass.
     *
     * @var list<string>
     */
    private array $anchors = [];

    /**
     * @param ChildFrame $childFrame The marks the next component root prints.
     *
     * @throws DuplicateAnchor When its anchor was marked before on this pass.
     */
    public function set(ChildFrame $childFrame): void
    {
        if ($childFrame->anchor !== '') {
            if (in_array($childFrame->anchor, $this->anchors, true)) {
                throw new DuplicateAnchor($childFrame->anchor);
            }

            $this->anchors[] = $childFrame->anchor;
        }

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
