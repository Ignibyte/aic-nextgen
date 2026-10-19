<?php

declare(strict_types=1);

namespace AIC\Http\Domain;

/**
 * Collects the bracketed input fields while InputParser reads them: list fields
 * (`name[]=v`) and map fields (`name[key]=v`).
 */
final class FieldGroups
{
    /**
     * List fields by base name.
     *
     * @var array<string, list<string>>
     */
    private array $lists = [];

    /**
     * Map fields by base name.
     *
     * @var array<string, array<string, string>>
     */
    private array $maps = [];

    /**
     * Add one bracketed field.
     *
     * @param string $base  The name before the bracket.
     * @param string $key   The key between the brackets; empty for a list entry.
     * @param string $value The decoded value.
     */
    public function add(string $base, string $key, string $value): void
    {
        if ($key === '') {
            $this->lists[$base][] = $value;

            return;
        }

        $this->maps[$base][$key] = $value;
    }

    /**
     * @return array<string, list<string>> The list fields by base name.
     */
    public function lists(): array
    {
        return $this->lists;
    }

    /**
     * @return array<string, array<string, string>> The map fields by base name.
     */
    public function maps(): array
    {
        return $this->maps;
    }
}
