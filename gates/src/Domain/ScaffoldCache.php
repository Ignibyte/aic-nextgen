<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * gate:33's record of the scaffolds it last saw pass (#300): each kind by the
 * hash of its folder and the gates that judged it, so an unchanged kind is not
 * made and judged again.
 */
interface ScaffoldCache
{
    /**
     * @param string $kind A kind.
     * @param string $key  The hash of its folder and the judging gates.
     *
     * @return bool Whether that kind passed with that key.
     */
    public function passed(string $kind, string $key): bool;

    /**
     * @param string $kind A kind that passed.
     * @param string $key  Its key.
     */
    public function record(string $kind, string $key): void;
}
