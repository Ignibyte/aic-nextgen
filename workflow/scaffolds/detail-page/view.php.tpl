<?php

declare(strict_types=1);

namespace {namespace}\Service;

use AIC\Component\Application\DetailParts;
use AIC\Component\Contracts\Component;
use AIC\Component\Domain\Heading;
use AIC\Component\Domain\KeyedComponent;
use AIC\Component\Domain\Stack;
use AIC\Component\Domain\Tabs;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Domain\FieldType;
use {namespace}\Repository\{stem}DetailRepository;
use InvalidArgumentException;

use function rawurlencode;

/**
 * The components of GET {route}: the row's heading, then its tabs and the
 * open tab's fields, one part (`@{anchor}`) a tab refreshes in place; made by
 * bin/aic make:detail-page (#341).
 */
final readonly class {stem}DetailView
{
    /**
     * Each tab's name and label, in order; the first opens when `tab` names none.
     */
    public const array TABS = [
{tabs}    ];

    /**
     * The rows' path.
     */
    private const string PATH = '{path}';

    /**
     * The part the tabs refresh.
     */
    private const string ANCHOR = '{anchor}';

    /**
     * Each tab's fields, by the tab's name, in order.
     */
    private const array PANELS = [
{panels}    ];

    /**
     * Each field's term.
     */
    private const array TERMS = [
{terms}    ];

    /**
{viewDocParams}     */
    public function __construct(
        private {stem}DetailRepository ${variable}DetailRepository,
        private DetailParts $detailParts,
    ) {
    }

    /**
     * @param string $id  The row's key, as the path names it.
     * @param string $tab The open tab, one of TABS.
     *
     * @throws QueryException           When the row cannot be read, or a field is missing from it or of another type.
     * @throws InvalidArgumentException When a field or the anchor breaks its grammar.
     *
     * @return list<Component>|null The page's components, in order; null when there is no such row.
     */
    public function components(string $id, string $tab): ?array
    {
        $row = $this->{variable}DetailRepository->find($id);

        if (!$row instanceof Row) {
            return null;
        }

        $heading = new Heading($this->detailParts->value($row, '{heading}', FieldType::{headingType}));
        $panel = $this->detailParts->panel($row, {stem}DetailRepository::FIELDS, self::TERMS, self::PANELS[$tab] ?? []);

        return [$heading, new KeyedComponent(self::ANCHOR, new Stack([$this->{variable}Tabs($id, $tab), $panel]), '', self::ANCHOR)];
    }

    /**
     * @param string $id  The row's key.
     * @param string $tab The open tab.
     *
     * @return Tabs A link per tab, the open one current, each refreshing `@{anchor}`.
     */
    private function {variable}Tabs(string $id, string $tab): Tabs
    {
        return $this->detailParts->tabs(self::PATH . '/' . rawurlencode($id), $tab, self::TABS, self::ANCHOR);
    }
}
