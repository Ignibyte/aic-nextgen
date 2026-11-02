<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\CodeBlock;
use AIC\Component\Domain\FilterForm;
use AIC\Component\Domain\Stack;
use AIC\Component\Domain\Table;
use AIC\Component\Domain\Tabs;
use AIC\Component\Domain\Timeline;
use AIC\Profiler\Domain\ProfilerLayout;

/**
 * The fourth atom group (#230): the data atoms the profiler pages are built
 * from, generic enough for any app, and the profiler's own document.
 */
final readonly class ProfilerAtoms
{
    /**
     * @return list<array{class-string<Component>, string}> Each atom's class and its template's name.
     */
    public function declarations(): array
    {
        return [
            [Table::class, 'table'],
            [Tabs::class, 'tabs'],
            [Timeline::class, 'timeline'],
            [CodeBlock::class, 'code-block'],
            [Stack::class, 'stack'],
            [FilterForm::class, 'filter-form'],
            [ProfilerLayout::class, 'profiler-layout'],
        ];
    }
}
