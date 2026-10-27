<?php

declare(strict_types=1);

namespace AIC\Entity\Domain;

/**
 * One condition of an any-of group (#184): a field, how it compares, and the
 * value it is compared with; null with Equals or NotEquals asks whether it is
 * null.
 */
final readonly class Condition
{
    /**
     * @param string               $field      A field of the queried entity type.
     * @param string|int|bool|null $value      The value it is compared with.
     * @param Comparison           $comparison How it is compared.
     */
    public function __construct(
        public string $field,
        public string|int|bool|null $value,
        public Comparison $comparison = Comparison::Equals,
    ) {
    }
}
