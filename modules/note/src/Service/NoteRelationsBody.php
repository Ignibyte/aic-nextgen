<?php

declare(strict_types=1);

namespace App\Note\Service;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Database\Contracts\Rows;
use AIC\Entity\Application\RelatedRows;
use JsonSerializable;
use Override;

/**
 * GET /notes/relations' body (#306), each line by note title, never by key:
 * the notes with their tag count and stock price sum, the notes the two
 * filters kept, each relation's loaded rows, and each mention's subject.
 */
final readonly class NoteRelationsBody implements JsonSerializable
{
    /**
     * @param Rows                                         $notes    The notes, with `tags_count` and `stock_items_sum_price`.
     * @param array{Rows, Rows}                            $filters  The notes with a red tag, then those with no tag.
     * @param array{RelatedRows, RelatedRows, RelatedRows} $related  The notes' tags and stock items, the mentions' subjects.
     * @param Rows                                         $mentions The first note's mentions.
     */
    public function __construct(
        private Rows $notes,
        private array $filters,
        private array $related,
        private Rows $mentions,
    ) {
    }

    /**
     * @throws QueryException When a row lacks a column it reads.
     *
     * @return array<string, list<string>> The lines, by what they show.
     */
    #[Override]
    public function jsonSerialize(): array
    {
        [$tags, $stock, $subjects] = $this->related;
        $lines = ['counted' => [], 'stock' => [], 'tags' => []];

        foreach ($this->notes as $note) {
            $title = $note->string('title');
            $lines['counted'][] = $title . ': ' . $note->int('tags_count') . ' tag(s), stock ' . $note->string('stock_items_sum_price');
            $lines['tags'] = [...$lines['tags'], ...self::each($title, $tags->rowsOf($note->int('id')), 'tag', '')];
            $lines['stock'] = [...$lines['stock'], ...self::each($title, $stock->rowsOf($note->int('id')), 'name', 'pivot_amount')];
        }

        return [
            ...$lines,
            'mentions' => $this->subjects($subjects),
            'tagged' => self::titles($this->filters[0]),
            'untagged' => self::titles($this->filters[1]),
        ];
    }

    /**
     * @param RelatedRows $relatedRows The mentions' subjects.
     *
     * @throws QueryException When a row lacks a column.
     *
     * @return list<string> Each mention's type and its subject's title or name.
     */
    private function subjects(RelatedRows $relatedRows): array
    {
        $lines = [];

        foreach ($this->mentions as $mention) {
            $type = $mention->string('subject_type');
            $subject = $relatedRows->one($mention->int('id'));
            $lines[] = $type . ': ' . ($subject instanceof Row ? $subject->string($type === 'note' ? 'title' : 'name') : 'none');
        }

        return $lines;
    }

    /**
     * @param string $title  An owner note's title.
     * @param Rows   $rows   Its related rows.
     * @param string $column The column each line shows.
     * @param string $extra  A second column it shows after ` x `; empty for none.
     *
     * @throws QueryException When a row lacks a column.
     *
     * @return list<string> One line per related row.
     */
    private static function each(string $title, Rows $rows, string $column, string $extra): array
    {
        $lines = [];

        foreach ($rows as $row) {
            $lines[] = $title . ': ' . $row->string($column) . ($extra === '' ? '' : ' x ' . $row->string($extra));
        }

        return $lines;
    }

    /**
     * @param Rows $rows Notes.
     *
     * @throws QueryException When a row lacks its title.
     *
     * @return list<string> Their titles.
     */
    private static function titles(Rows $rows): array
    {
        $titles = [];

        foreach ($rows as $row) {
            $titles[] = $row->string('title');
        }

        return $titles;
    }
}
