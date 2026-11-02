<?php

declare(strict_types=1);

namespace AIC\Component\Contracts;

/**
 * Told what the Renderer rendered and how long it took (#229): the seam a
 * profiler reads the component tree by. A Renderer with no observer tells
 * no one.
 */
interface RenderObserver
{
    /**
     * @param string $path         The Tessera path it rendered at (`content/c0/NoteList.1`); an atom shares the path of
     *                             the component it sits in.
     * @param string $component    The rendered class.
     * @param int    $microseconds How long its template took, its children included.
     */
    public function rendered(string $path, string $component, int $microseconds): void;

    /**
     * @param int $microseconds How long one whole render took: a document, a fragment answer or a stream's elements.
     */
    public function passed(int $microseconds): void;
}
