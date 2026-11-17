<?php

declare(strict_types=1);

namespace Gates\Domain;

use function var_export;

/**
 * One entry of a generated per-module class (#93): the object built, under its
 * key, wrapped by the context when it is a controller, with what its route
 * needs (#304).
 */
final readonly class ContextEntry
{
    /**
     * @param string      $key         The entry's key as code (`FaqPage::class`, `'user:add'`); empty in a list.
     * @param WiredObject $wiredObject The object built.
     * @param string      $wrap        The context method that wraps it (`page`, `api`, `stream`); empty for none.
     * @param string      $need        What its route needs (#304), the wrap's second argument; empty for nothing.
     */
    public function __construct(
        public string $key,
        public WiredObject $wiredObject,
        public string $wrap = '',
        public string $need = '',
    ) {
    }

    /**
     * @return bool Whether it reads the context: the context wraps it, or building it reads a root.
     */
    public function readsRoots(): bool
    {
        return $this->wrap !== '' || $this->wiredObject->readsRoots();
    }

    /**
     * @param string $property The context's property, `handlerContext`.
     * @param string $indent   The indent of a split call's arguments.
     *
     * @return array{string, string, string} The wrap's opening, its one-line close with the need (#304), and the need's
     *                                       line when the call is split; all empty for no wrap.
     */
    public function wrapping(string $property, string $indent): array
    {
        if ($this->wrap === '') {
            return ['', '', ''];
        }

        $open = '$this->' . $property . '->' . $this->wrap . '(';
        $need = var_export($this->need, true);

        return $this->need === ''
            ? [$open, ')', '']
            : [$open, ', ' . $need . ')', "\n" . $indent . $need . ','];
    }
}
