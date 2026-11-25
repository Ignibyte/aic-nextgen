<?php

declare(strict_types=1);

namespace App\Scaffold\Service;

use AIC\Component\Application\DetailParts;
use AIC\Component\Contracts\Component;
use AIC\Component\Domain\Heading;
use AIC\Component\Domain\KeyedComponent;
use AIC\Component\Domain\Stack;
use AIC\Component\Domain\Tabs;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Domain\FieldType;
use App\Scaffold\Repository\ScaffoldNoteDetailRepository;
use InvalidArgumentException;

use function rawurlencode;

/**
 * The components of GET /scaffold/notes/{id:int}: the row's heading, then its tabs and the
 * open tab's fields, one part (`@scaffold-note-tab`) a tab refreshes in place; made by
 * bin/aic make:detail-page (#341).
 */
final readonly class ScaffoldNoteDetailView
{
    /**
     * Each tab's name and label, in order; the first opens when `tab` names none.
     */
    public const array TABS = [
        ['summary', 'Summary'],
        ['body', 'Body'],
    ];

    /**
     * The rows' path.
     */
    private const string PATH = '/scaffold/notes';

    /**
     * The part the tabs refresh.
     */
    private const string ANCHOR = 'scaffold-note-tab';

    /**
     * Each tab's fields, by the tab's name, in order.
     */
    private const array PANELS = [
        'body' => [
            'body',
        ],
        'summary' => [
            'title',
        ],
    ];

    /**
     * Each field's term.
     */
    private const array TERMS = [
        'body' => 'Body',
        'title' => 'Title',
    ];

    /**
     * @param ScaffoldNoteDetailRepository $scaffoldNoteDetailRepository Finds the row.
     * @param DetailParts                  $detailParts                  Makes the heading, the panel and the tabs.
     */
    public function __construct(
        private ScaffoldNoteDetailRepository $scaffoldNoteDetailRepository,
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
        $row = $this->scaffoldNoteDetailRepository->find($id);

        if (!$row instanceof Row) {
            return null;
        }

        $heading = new Heading($this->detailParts->value($row, 'title', FieldType::Text));
        $panel = $this->detailParts->panel($row, ScaffoldNoteDetailRepository::FIELDS, self::TERMS, self::PANELS[$tab] ?? []);

        return [$heading, new KeyedComponent(self::ANCHOR, new Stack([$this->scaffoldNoteTabs($id, $tab), $panel]), '', self::ANCHOR)];
    }

    /**
     * @param string $id  The row's key.
     * @param string $tab The open tab.
     *
     * @return Tabs A link per tab, the open one current, each refreshing `@scaffold-note-tab`.
     */
    private function scaffoldNoteTabs(string $id, string $tab): Tabs
    {
        return $this->detailParts->tabs(self::PATH . '/' . rawurlencode($id), $tab, self::TABS, self::ANCHOR);
    }
}
