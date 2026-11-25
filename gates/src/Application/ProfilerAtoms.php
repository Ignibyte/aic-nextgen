<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\CodeBlock;
use AIC\Component\Domain\DescriptionList;
use AIC\Component\Domain\FilterBar;
use AIC\Component\Domain\FilterForm;
use AIC\Component\Domain\Pager;
use AIC\Component\Domain\Stack;
use AIC\Component\Domain\Table;
use AIC\Component\Domain\Tabs;
use AIC\Component\Domain\Timeline;
use AIC\Profiler\Domain\ProfilerDebug;
use AIC\Profiler\Domain\ProfilerLayout;
use AIC\Profiler\Domain\ProfilerToolbar;

/**
 * The fourth atom group (#230): the data atoms the profiler pages are built
 * from, generic enough for any app, and the profiler's own document, toolbar
 * and debug meta (#231), a list's filter bar and pager (#324), and a detail page's
 * description list (#341).
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
            [FilterBar::class, 'filter-bar'],
            [Pager::class, 'pager'],
            [DescriptionList::class, 'description-list'],
            [ProfilerLayout::class, 'profiler-layout'],
            [ProfilerToolbar::class, 'profiler-toolbar'],
            [ProfilerDebug::class, 'profiler-debug'],
        ];
    }
}
