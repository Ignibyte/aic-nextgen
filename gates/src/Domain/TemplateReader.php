<?php

declare(strict_types=1);

namespace Gates\Domain;

use InvalidArgumentException;

/**
 * Reads a component template for the build (#97): validates it, resolves its
 * component and lowers it to nodes. Gates' template layer implements it; the
 * build in `Gates\Application` asks only this.
 */
interface TemplateReader
{
    /**
     * @param string               $path            The template, relative to the repo root, for the messages.
     * @param string               $source          Its source.
     * @param TemplateBinding|null $templateBinding The explicit declaration, absent only for the bounded legacy reader.
     *
     * @throws InvalidArgumentException When it breaks the grammar or a type rule; the message holds one line per refusal.
     *
     * @return TemplateUnit The template read.
     */
    public function read(string $path, string $source, ?TemplateBinding $templateBinding = null): TemplateUnit;
}
