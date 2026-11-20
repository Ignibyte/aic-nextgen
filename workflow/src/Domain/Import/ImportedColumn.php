<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Import;

/**
 * One column of an imported table (#318).
 */
final readonly class ImportedColumn
{
    /**
     * @param string        $name         The column.
     * @param ImportedType  $importedType Its type.
     * @param bool          $nullable     Whether it may be null.
     * @param CodeNode|null $default      Its default as a term, if it has one.
     * @param CodeNode|null $generated    The term a stored generated column is made from.
     * @param ColumnKey     $columnKey    Whether the database makes its value as a key.
     * @param list<string>  $choices      The values a choice column may hold, read from its check; empty for none.
     */
    public function __construct(
        public string $name,
        public ImportedType $importedType,
        public bool $nullable,
        public ?CodeNode $default = null,
        public ?CodeNode $generated = null,
        public ColumnKey $columnKey = ColumnKey::None,
        public array $choices = [],
    ) {
    }

    /**
     * @param ColumnKey $columnKey How the database makes it.
     *
     * @return self The column as a key the database makes.
     */
    public function withKey(ColumnKey $columnKey): self
    {
        return new self($this->name, $this->importedType, $this->nullable, $this->default, $this->generated, $columnKey, $this->choices);
    }

    /**
     * @param list<string> $choices The values.
     *
     * @return self The column as a choice of them.
     */
    public function withChoices(array $choices): self
    {
        return new self($this->name, $this->importedType, $this->nullable, $this->default, $this->generated, $this->columnKey, $choices);
    }

    /**
     * @return self The column, never null.
     */
    public function notNull(): self
    {
        return new self($this->name, $this->importedType, false, $this->default, $this->generated, $this->columnKey, $this->choices);
    }

    /**
     * @param CodeNode $codeNode The default, a term.
     *
     * @return self The column with it.
     */
    public function withDefault(CodeNode $codeNode): self
    {
        return new self($this->name, $this->importedType, $this->nullable, $codeNode, $this->generated, $this->columnKey, $this->choices);
    }

    /**
     * @param CodeNode $codeNode The term it is generated from.
     *
     * @return self The column, generated.
     */
    public function withGenerated(CodeNode $codeNode): self
    {
        return new self($this->name, $this->importedType, $this->nullable, $this->default, $codeNode, $this->columnKey, $this->choices);
    }
}
