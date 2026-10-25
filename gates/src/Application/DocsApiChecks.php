<?php

declare(strict_types=1);

namespace Gates\Application;

use BackedEnum;
use Gates\Domain\MarkdownDocument;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionMethod;
use UnitEnum;

use function array_diff;
use function array_filter;
use function array_map;
use function array_values;
use function is_string;
use function str_starts_with;
use function trim;

/** Compare explicit documentation table inventories with the public source contracts. */
final readonly class DocsApiChecks
{
    /**
     * @param MarkdownDocument $markdownDocument The page owning the API table.
     * @param class-string     $class            The public contract being described.
     *
     * @return list<string> Missing or unsupported method rows, excluding the constructor.
     */
    public function methods(MarkdownDocument $markdownDocument, string $class): array
    {
        $reflectionClass = new ReflectionClass($class);
        $methods = array_filter(
            $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC),
            static fn (ReflectionMethod $reflectionMethod): bool => !$reflectionMethod->isConstructor(),
        );
        $label = $reflectionClass->getShortName();

        return $this->inventory($markdownDocument, $label, array_values(array_map(
            static fn (ReflectionMethod $reflectionMethod): string => $label . '::' . $reflectionMethod->getName() . '()',
            $methods,
        )));
    }

    /**
     * @param MarkdownDocument $markdownDocument The page owning the constant table.
     * @param class-string     $class            The public string constants being described.
     *
     * @return list<string> Missing, altered or unsupported constant rows.
     */
    public function constants(MarkdownDocument $markdownDocument, string $class): array
    {
        $reflectionClass = new ReflectionClass($class);
        $rows = [];

        foreach ($reflectionClass->getReflectionConstants(ReflectionClassConstant::IS_PUBLIC) as $reflectionClassConstant) {
            $value = [$reflectionClassConstant->getValue()];

            if (!is_string($value[0])) {
                continue;
            }

            $rows[] = $reflectionClass->getShortName() . '::' . $reflectionClassConstant->getName() . ' = ' . $value[0];
        }

        return $this->inventory($markdownDocument, $reflectionClass->getShortName(), $rows);
    }

    /**
     * @param MarkdownDocument       $markdownDocument The page owning the case table.
     * @param class-string<UnitEnum> $class            The enum being described.
     *
     * @return list<string> Missing, altered or unsupported enum rows.
     */
    public function cases(MarkdownDocument $markdownDocument, string $class): array
    {
        $label = (new ReflectionClass($class))->getShortName();
        $rows = array_map(
            static fn (UnitEnum $unitEnum): string => $label . '::' . $unitEnum->name . ' = '
                . ($unitEnum instanceof BackedEnum ? (string) $unitEnum->value : $unitEnum->name),
            $class::cases(),
        );

        return $this->inventory($markdownDocument, $label, $rows);
    }

    /**
     * @param MarkdownDocument $markdownDocument The page with a first-column inventory.
     * @param string           $label            The exact inventory prefix before ::.
     * @param list<string>     $expected         The actual source entries, including names and values.
     *
     * @return list<string> A finding for each missing or unsupported row.
     */
    public function inventory(MarkdownDocument $markdownDocument, string $label, array $expected): array
    {
        $actual = [];

        foreach ($markdownDocument->firstCells() as [, $cell]) {
            $entry = trim($cell, '`');

            if (!str_starts_with($entry, $label . '::')) {
                continue;
            }

            $actual[] = $entry;
        }

        return [
            ...array_map(
                static fn (string $entry): string => $markdownDocument->page . ': missing inventory row `' . $entry . '`; match code.',
                array_diff($expected, $actual),
            ),
            ...array_map(
                static fn (string $entry): string => $markdownDocument->page . ': unsupported inventory row `' . $entry . '`; match code.',
                array_diff($actual, $expected),
            ),
        ];
    }
}
