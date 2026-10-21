<?php

declare(strict_types=1);

namespace AIC\Tessera\Domain;

/**
 * One component marker found in markup (#70): where it starts and ends, and
 * the key an opening marker carries; a closing marker has none.
 */
final readonly class Marker
{
    /**
     * @param int    $start Where `<!--` starts.
     * @param int    $end   Just after `-->`; -1 for an opening marker that never closes.
     * @param string $key   The opening marker's key; empty for `<!--/t:c-->`.
     */
    public function __construct(
        public int $start,
        public int $end,
        public string $key,
    ) {
    }

    /**
     * @return bool Whether it opens a component.
     */
    public function opens(): bool
    {
        return $this->key !== '';
    }
}
