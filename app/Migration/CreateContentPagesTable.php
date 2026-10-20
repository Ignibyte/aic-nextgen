<?php

declare(strict_types=1);

namespace App\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * The content pages (#50): text by slug.
 */
final readonly class CreateContentPagesTable implements Migration
{
    /**
     * @return string Its version.
     */
    #[Override]
    public function version(): string
    {
        return '2026_10_20_000007_create_content_pages';
    }

    /**
     * @param Schema $schema The schema to describe it on.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->create('content_pages')
            ->string('slug', 100)
            ->string('title', 200)
            ->text('body')
            ->timestampTz('updated_at')
            ->primary(['slug']);
    }
}
