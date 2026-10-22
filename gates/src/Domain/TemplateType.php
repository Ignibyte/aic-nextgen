<?php

declare(strict_types=1);

namespace Gates\Domain;

use function array_filter;
use function array_values;
use function count;
use function in_array;

/**
 * A template expression's static type, as the build reads it (#97): the kinds
 * it may be, the class when it is an object, the element type when it is a
 * list. The escaping, the conditions and the null tests are decided from it.
 */
final readonly class TemplateType
{
    /**
     * @param list<TemplateTypeKind> $kinds   The kinds it may be.
     * @param string                 $class   The class, when it is an object; empty otherwise.
     * @param TemplateType|null      $element A list's element type.
     */
    public function __construct(
        public array $kinds,
        public string $class = '',
        public ?self $element = null,
    ) {
    }

    /**
     * @param TemplateTypeKind $templateTypeKind A kind.
     *
     * @return bool Whether it is exactly that kind.
     */
    public function only(TemplateTypeKind $templateTypeKind): bool
    {
        return $this->kinds === [$templateTypeKind];
    }

    /**
     * @param TemplateTypeKind $templateTypeKind A kind.
     *
     * @return bool Whether it may be that kind.
     */
    public function may(TemplateTypeKind $templateTypeKind): bool
    {
        return in_array($templateTypeKind, $this->kinds, true);
    }

    /**
     * @return bool Whether every kind is string, int or float: a value `<?= ?>` writes as text.
     */
    public function scalar(): bool
    {
        $text = array_filter(
            $this->kinds,
            static fn (TemplateTypeKind $templateTypeKind): bool => in_array(
                $templateTypeKind,
                [TemplateTypeKind::String, TemplateTypeKind::Int, TemplateTypeKind::Float],
                true,
            ),
        );

        return $this->kinds !== [] && count($text) === count($this->kinds);
    }

    /**
     * @return self It without null.
     */
    public function withoutNull(): self
    {
        $kinds = array_values(array_filter(
            $this->kinds,
            static fn (TemplateTypeKind $templateTypeKind): bool => $templateTypeKind !== TemplateTypeKind::Null,
        ));

        return new self($kinds, $this->class, $this->element);
    }

    /**
     * @param self $other Another type.
     *
     * @return self Either of the two: their kinds together, the class and element of whichever has one.
     */
    public function join(self $other): self
    {
        $kinds = $this->kinds;

        foreach ($other->kinds as $kind) {
            if (in_array($kind, $kinds, true)) {
                continue;
            }

            $kinds[] = $kind;
        }

        return new self($kinds, $this->class === '' ? $other->class : $this->class, $this->element ?? $other->element);
    }
}
