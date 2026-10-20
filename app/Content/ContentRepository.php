<?php

declare(strict_types=1);

namespace App\Content;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;

use function array_map;

/**
 * The content pages, in `content_pages` (#50).
 */
final readonly class ContentRepository
{
    /**
     * @param Database $database The database.
     */
    public function __construct(
        private Database $database,
    ) {
    }

    /**
     * @throws QueryException When the table cannot be read.
     *
     * @return list<ContentPage> Every page, by title.
     */
    public function all(): array
    {
        return array_map(
            static fn (Row $row): ContentPage => new ContentPage($row->string('slug'), $row->string('title'), $row->string('body')),
            $this->database->fetchAll('SELECT slug, title, body FROM content_pages ORDER BY title, slug', []),
        );
    }

    /**
     * @param string $slug A page's slug.
     *
     * @throws QueryException When the table cannot be read.
     *
     * @return ContentPage|null The page; null when there is none.
     */
    public function find(string $slug): ?ContentPage
    {
        $row = $this->database->fetchOne(
            "SELECT count(*) AS found, coalesce(max(title), '') AS title, coalesce(max(body), '') AS body"
            . ' FROM content_pages WHERE slug = ?',
            [$slug],
        );

        return $row->int('found') === 0 ? null : new ContentPage($slug, $row->string('title'), $row->string('body'));
    }

    /**
     * Creates the page, or rewrites it.
     *
     * @param ContentPage $contentPage The page.
     *
     * @throws QueryException When it cannot be stored.
     */
    public function save(ContentPage $contentPage): void
    {
        $this->database->execute(
            'INSERT INTO content_pages (slug, title, body, updated_at) VALUES (?, ?, ?, now())'
            . ' ON CONFLICT (slug) DO UPDATE SET title = EXCLUDED.title, body = EXCLUDED.body, updated_at = now()',
            [$contentPage->slug, $contentPage->title, $contentPage->body],
        );
    }
}
