<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Import;

use function in_array;

/**
 * An imported column's type as a `ColumnTypes` call (#318): `string` with `100`, `numeric` with `8, 2`, `jsonb`.
 */
final readonly class ImportedType
{
    private const array BUILDER = ['string', 'text', 'integer', 'bigInteger', 'timestampTz', 'date', 'numeric', 'boolean'];

    /**
     * @param string $method    The `ColumnTypes` method.
     * @param string $arguments Its arguments after the name, as PHP: `100`, `8, 2` or empty.
     * @param string $spelled   The type as the dump spells it, for a message.
     * @param string $note      What the import prints when it translates the type (#339), after the column's name;
     *                          empty when Schema declares it as it is.
     */
    public function __construct(
        public string $method,
        public string $arguments,
        public string $spelled,
        public string $note = '',
    ) {
    }

    /**
     * @return bool Whether `TableBuilder` has a method of the same name taking the column's name first; a
     *              timestamp's precision is not one of its arguments (#339).
     */
    public function onBuilder(): bool
    {
        return in_array($this->method, self::BUILDER, true) && ($this->method !== 'timestampTz' || $this->arguments === '');
    }

    /**
     * @return bool Whether the column holds text, which a choice may replace.
     */
    public function isText(): bool
    {
        return $this->method === 'text' || $this->method === 'string';
    }
}
