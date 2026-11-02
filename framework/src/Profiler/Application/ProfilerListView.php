<?php

declare(strict_types=1);

namespace AIC\Profiler\Application;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\FilterForm;
use AIC\Component\Domain\Heading;
use AIC\Component\Domain\KeyedComponent;
use AIC\Component\Domain\Paragraph;
use AIC\Component\Domain\Stack;
use AIC\Component\Domain\Tab;
use AIC\Component\Domain\Table;
use AIC\Component\Domain\TableCell;
use AIC\Component\Domain\TableRow;
use AIC\Component\Domain\Tabs;
use AIC\Profiler\Domain\ProfileFilter;
use AIC\Profiler\Domain\ProfileRow;
use InvalidArgumentException;

use function count;
use function implode;
use function rawurlencode;
use function sprintf;

/**
 * The profiler's list page as components (#230): a path filter, the kind
 * filters and the table, the last two one Tessera target (`@profiles`) that a
 * filter answers in place and a poll keeps current.
 */
final readonly class ProfilerListView
{
    /** The list's address. */
    private const string PATH = ProfilerDocuments::PATH;

    /** The anchor the filters target. */
    private const string ANCHOR = 'profiles';

    /** What the list is. */
    private const string INTRO = 'The newest 100 requests recorded with AIC_PROFILER=1, newest first.'
        . ' Open one to see its times, statements, templates and Tessera exchange.';

    /** The kinds the filter offers, by label; empty for any. */
    private const array KINDS = ['All' => '', 'Page' => 'Page', 'Api' => 'Api', 'Stream' => 'Stream'];

    /**
     * @param list<ProfileRow> $profileRows   The rows that pass.
     * @param ProfileFilter    $profileFilter The filter they passed.
     *
     * @throws InvalidArgumentException When a key or an anchor breaks the grammar.
     *
     * @return list<Component> The page's content.
     */
    public function content(array $profileRows, ProfileFilter $profileFilter): array
    {
        $target = '@' . self::ANCHOR;

        return [
            new Heading('Requests'),
            new Paragraph(self::INTRO, ''),
            new FilterForm(self::PATH, 'path', $profileFilter->path, 'Path contains', $target),
            new KeyedComponent(
                self::ANCHOR,
                new Stack([$this->kinds($profileFilter), $this->table($profileRows, $profileFilter)]),
                '',
                self::ANCHOR,
            ),
        ];
    }

    /**
     * @return list<Component> A 404's content: why, and the way back.
     */
    public function missing(): array
    {
        return [
            new Heading('Not found'),
            new Paragraph('No kept profile has that id: the profiler keeps the newest 500.', ''),
            new Tabs([new Tab('All requests', self::PATH)], '', 'Requests'),
        ];
    }

    /**
     * @param ProfileFilter $profileFilter The current filter.
     *
     * @return Tabs One tab per kind, the current one marked.
     */
    private function kinds(ProfileFilter $profileFilter): Tabs
    {
        $tabs = [];

        foreach (self::KINDS as $label => $kind) {
            $href = $this->href(new ProfileFilter($profileFilter->path, $kind, $profileFilter->status));
            $tabs[] = new Tab($label, $href, $kind === $profileFilter->kind);
        }

        return new Tabs($tabs, '@' . self::ANCHOR, 'Kind');
    }

    /**
     * @param list<ProfileRow> $profileRows   The rows.
     * @param ProfileFilter    $profileFilter The filter, which the poll keeps.
     *
     * @return Table The table, polled every five seconds.
     */
    private function table(array $profileRows, ProfileFilter $profileFilter): Table
    {
        $rows = [];

        foreach ($profileRows as $profileRow) {
            $rows[] = new TableRow([
                new TableCell($profileRow->kept),
                new TableCell($profileRow->method),
                new TableCell($profileRow->path, self::PATH . '/' . $profileRow->id),
                new TableCell($profileRow->kind),
                new TableCell((string) $profileRow->status),
                new TableCell(sprintf('%.1f', $profileRow->microseconds / 1_000)),
                new TableCell((string) $profileRow->bytes),
                new TableCell((string) $profileRow->statements),
                new TableCell($this->tessera($profileRow)),
            ], $profileRow->status >= 500 ? 'warn' : '');
        }

        return new Table(
            ['Time', 'Method', 'Path', 'Kind', 'Status', 'ms', 'Bytes', 'SQL', 'Tessera'],
            $rows,
            sprintf('%d request(s)', count($rows)),
            $this->href($profileFilter),
        );
    }

    /**
     * @param ProfileRow $profileRow A row.
     *
     * @return string Its Tessera request's kind and targets; empty for a plain request.
     */
    private function tessera(ProfileRow $profileRow): string
    {
        if ($profileRow->tessera === '') {
            return '';
        }

        return $profileRow->targets === ''
            ? $profileRow->tessera
            : $profileRow->tessera . ' → ' . $profileRow->targets;
    }

    /**
     * @param ProfileFilter $profileFilter A filter.
     *
     * @return string The list's address with it as GET parameters.
     */
    private function href(ProfileFilter $profileFilter): string
    {
        $query = [];

        if ($profileFilter->path !== '') {
            $query[] = 'path=' . rawurlencode($profileFilter->path);
        }

        if ($profileFilter->kind !== '') {
            $query[] = 'kind=' . rawurlencode($profileFilter->kind);
        }

        if ($profileFilter->status !== 0) {
            $query[] = 'status=' . $profileFilter->status;
        }

        return $query === []
            ? self::PATH
            : self::PATH . '?' . implode('&', $query);
    }
}
