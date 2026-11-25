<?php

declare(strict_types=1);

namespace AIC\Entity\Application;

use AIC\Entity\Domain\FieldType;
use AIC\Entity\Domain\Output;
use InvalidArgumentException;

use function implode;

/**
 * Rows a derived query reads (#329): a SELECT the query layer built, its
 * values in the order of their marks, and its columns.
 */
final readonly class DerivedSource
{
    /**
     * @param string           $sql            A SELECT, or a union of SELECTs.
     * @param list<string|int> $params         Its values.
     * @param DerivedColumns   $derivedColumns Its columns.
     */
    public function __construct(
        public string $sql,
        public array $params,
        public DerivedColumns $derivedColumns,
    ) {
    }

    /**
     * @param list<Output> $outputs The columns to keep, in order; none for every one.
     *
     * @throws InvalidArgumentException When an output reads a column the rows lack, or two have one name.
     *
     * @return self The rows with those columns, a Numeric one as a number, so it compares and orders as one.
     */
    public function project(array $outputs): self
    {
        $derivedColumns = new DerivedColumns();
        $selected = [];
        $kept = $outputs === []
            ? $this->every()
            : $outputs;

        foreach ($kept as $output) {
            $selected[] = $this->read($output) . ' AS ' . $output->name;
            $derivedColumns->add($output->name, $this->type($output));
        }

        $sql = 'SELECT ' . implode(', ', $selected) . ' FROM (' . $this->sql . ') AS aic_source';

        return new self($sql, [...$this->texts($kept), ...$this->params], $derivedColumns);
    }

    /**
     * @param self   $derivedSource Other rows.
     * @param string $union         `UNION` or `UNION ALL`.
     *
     * @throws InvalidArgumentException When the two differ in a column's name, order or type.
     *
     * @return self Both's rows.
     */
    public function union(self $derivedSource, string $union): self
    {
        $this->derivedColumns->align($derivedSource->derivedColumns);
        $sql = '(' . $this->sql . ') ' . $union . ' (' . $derivedSource->sql . ')';

        return new self($sql, [...$this->params, ...$derivedSource->params], $this->derivedColumns);
    }

    /**
     * @throws InvalidArgumentException When a column's name is not an identifier.
     *
     * @return list<Output> Every column, under its own name.
     */
    private function every(): array
    {
        $outputs = [];

        foreach ($this->derivedColumns->names() as $name) {
            $outputs[] = new Output($name, $name);
        }

        return $outputs;
    }

    /**
     * @param Output $output A column to keep.
     *
     * @throws InvalidArgumentException When the rows have no such column.
     *
     * @return string Its expression: the column, as a number for a Numeric one, or the mark of a fixed text.
     */
    private function read(Output $output): string
    {
        if ($output->text !== null) {
            return '?::text';
        }

        $number = $this->type($output) === FieldType::Numeric
            ? '::numeric'
            : '';

        return 'aic_source.' . $output->column . $number;
    }

    /**
     * @param Output $output A column to keep.
     *
     * @throws InvalidArgumentException When the rows have no such column.
     *
     * @return FieldType Its type: Text for a fixed text.
     */
    private function type(Output $output): FieldType
    {
        return $output->text === null
            ? $this->derivedColumns->type($output->column)
            : FieldType::Text;
    }

    /**
     * @param list<Output> $outputs The columns to keep.
     *
     * @return list<string> Their fixed texts, in order.
     */
    private function texts(array $outputs): array
    {
        $texts = [];

        foreach ($outputs as $output) {
            $text = $output->text;

            if ($text === null) {
                continue;
            }

            $texts[] = $text;
        }

        return $texts;
    }
}
