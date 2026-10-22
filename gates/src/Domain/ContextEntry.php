<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * One entry of a generated per-module class (#93): the object built, under its
 * key, wrapped by the context when it is a controller.
 */
final readonly class ContextEntry
{
    /**
     * @param string      $key         The entry's key as code (`FaqPage::class`, `'user:add'`); empty in a list.
     * @param WiredObject $wiredObject The object built.
     * @param string      $wrap        The context method that wraps it (`page`, `api`, `stream`); empty for none.
     */
    public function __construct(
        public string $key,
        public WiredObject $wiredObject,
        public string $wrap = '',
    ) {
    }

    /**
     * @return bool Whether it reads the context: the context wraps it, or building it reads a root.
     */
    public function readsRoots(): bool
    {
        return $this->wrap !== '' || $this->wiredObject->readsRoots();
    }
}
