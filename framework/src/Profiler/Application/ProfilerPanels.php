<?php

declare(strict_types=1);

namespace AIC\Profiler\Application;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\CodeBlock;
use AIC\Component\Domain\Paragraph;
use AIC\Component\Domain\Stack;
use AIC\Component\Domain\Table;
use AIC\Component\Domain\TableAlign;
use AIC\Component\Domain\TableCell;
use AIC\Component\Domain\TableRow;
use AIC\Profiler\Domain\ProfileView;
use AIC\Profiler\Domain\StatementRecord;
use AIC\Profiler\Domain\TimelineBar;

use function array_count_values;
use function array_map;
use function count;
use function implode;
use function sprintf;

/**
 * One tab's panel of the profiler's detail page (#230), as plain components:
 * the request, its timeline, its statements (a repeated one marked), its
 * templates and its Tessera exchange.
 */
final readonly class ProfilerPanels
{
    /** The database tab's columns: the position, the SQL, the time and the rows (#240). */
    private const array STATEMENT_ALIGNMENTS = [TableAlign::End, TableAlign::Start, TableAlign::End, TableAlign::End];

    /** The templates tab's columns: the path, the component and the time (#240). */
    private const array TEMPLATE_ALIGNMENTS = [TableAlign::Start, TableAlign::Start, TableAlign::End];

    /**
     * @param TimelineBar $timelineBar Draws the times.
     */
    public function __construct(
        private TimelineBar $timelineBar = new TimelineBar(),
    ) {
    }

    /**
     * @param ProfileView $profileView The profile.
     * @param string      $tab         The open tab.
     *
     * @return Component The tab's panel.
     */
    public function panel(ProfileView $profileView, string $tab): Component
    {
        return match ($tab) {
            'timeline' => $this->timeline($profileView),
            'database' => $this->database($profileView),
            'templates' => $this->templates($profileView),
            'tessera' => $this->tessera($profileView),
            default => $this->request($profileView),
        };
    }

    /**
     * @param ProfileView $profileView The profile.
     *
     * @return Table The request: its route, handler, user, query, header and field names.
     */
    private function request(ProfileView $profileView): Table
    {
        $requestDetail = $profileView->requestDetail;

        return $this->pairs('The request', [
            ['Kind', $profileView->profileRow->kind],
            ['Handler', $profileView->handler],
            ['User', $profileView->user],
            ['Query', $requestDetail->query],
            ['Headers', implode(', ', $requestDetail->headers)],
            ['Fields', implode(', ', $requestDetail->fields)],
        ]);
    }

    /**
     * @param ProfileView $profileView The profile.
     *
     * @return Stack The bar and the times it draws.
     */
    private function timeline(ProfileView $profileView): Stack
    {
        $profileTimes = $profileView->profileTimes;

        return new Stack([
            $this->timelineBar->timeline($profileTimes),
            $this->pairs('Microseconds', [
                ['Total', (string) $profileTimes->total],
                ['Middleware', (string) $profileTimes->middleware],
                ['Handler', (string) $profileTimes->handler],
                ['Render', (string) $profileTimes->render],
            ]),
        ]);
    }

    /**
     * @param ProfileView $profileView The profile.
     *
     * @return Table Each statement, a repeated one marked.
     */
    private function database(ProfileView $profileView): Table
    {
        $sql = array_map(static fn (StatementRecord $statementRecord): string => $statementRecord->sql, $profileView->statements);
        $counts = array_count_values($sql);
        $rows = [];
        $repeated = 0;

        foreach ($profileView->statements as $position => $statementRecord) {
            $repeat = ($counts[$statementRecord->sql] ?? 1) > 1;
            $repeated += $repeat
                ? 1
                : 0;
            $rows[] = new TableRow([
                new TableCell((string) ($position + 1)),
                new TableCell($statementRecord->sql),
                new TableCell((string) $statementRecord->microseconds),
                new TableCell((string) $statementRecord->rows),
            ], $repeat ? 'warn' : '');
        }

        $caption = sprintf('%d statement(s), %d of them repeated', count($rows), $repeated);

        return new Table(['#', 'SQL', 'µs', 'Rows'], $rows, $caption, alignments: self::STATEMENT_ALIGNMENTS);
    }

    /**
     * @param ProfileView $profileView The profile.
     *
     * @return Table Each template, in the order each finished.
     */
    private function templates(ProfileView $profileView): Table
    {
        $rows = [];

        foreach ($profileView->components as $componentRecord) {
            $rows[] = new TableRow([
                new TableCell($componentRecord->path),
                new TableCell($componentRecord->component),
                new TableCell((string) $componentRecord->microseconds),
            ]);
        }

        $caption = sprintf('%d template(s), a child before its parent', count($rows));

        return new Table(['Path', 'Component', 'µs'], $rows, $caption, alignments: self::TEMPLATE_ALIGNMENTS);
    }

    /**
     * @param ProfileView $profileView The profile.
     *
     * @return Stack The Tessera headers both ways, the answer's size against the page's, and its markup.
     */
    private function tessera(ProfileView $profileView): Stack
    {
        $bytes = $profileView->profileRow->bytes;
        $size = $profileView->fullBytes === 0 || $profileView->profileRow->tessera === ''
            ? sprintf('%d bytes.', $bytes)
            : sprintf('%d bytes; the newest whole page for this path was %d bytes.', $bytes, $profileView->fullBytes);
        $body = $profileView->requestDetail->body;

        return new Stack([
            $this->pairs('Asked', $this->listed($profileView->tessera)),
            $this->pairs('Answered', $this->listed($profileView->answer)),
            new Paragraph($size, ''),
            $body === '' ? new Paragraph('Not a fragment answer: no markup kept.', '') : new CodeBlock($body),
        ]);
    }

    /**
     * @param array<string, string> $values Values by name.
     *
     * @return list<array{string, string}> Them as pairs, in their order.
     */
    private function listed(array $values): array
    {
        $pairs = [];

        foreach ($values as $name => $value) {
            $pairs[] = [$name, $value];
        }

        return $pairs;
    }

    /**
     * @param string                      $caption What the pairs are.
     * @param list<array{string, string}> $pairs   Each a name and its value, in order.
     *
     * @return Table A table of names and values.
     */
    private function pairs(string $caption, array $pairs): Table
    {
        $rows = [];

        foreach ($pairs as [$name, $value]) {
            $rows[] = new TableRow([new TableCell($name), new TableCell($value)]);
        }

        return new Table(['Name', 'Value'], $rows, $caption);
    }
}
