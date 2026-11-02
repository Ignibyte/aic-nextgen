<?php

declare(strict_types=1);

namespace AIC\Component\Contracts;

/**
 * Told what the Renderer rendered and how long it took (#229): the seam a
 * profiler reads the component tree by. It may also add a component to the
 * end of a whole document's head and body (#231). A Renderer with no observer
 * tells no one and adds nothing.
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

    /**
     * @return Component|null What a whole document gains at the end of its head (#231), such as a debug meta; null for
     *                        nothing.
     */
    public function headEnd(): ?Component;

    /**
     * @return Component|null What a whole document gains at the end of its body (#231), such as a toolbar; null for
     *                        nothing.
     */
    public function bodyEnd(): ?Component;
}
