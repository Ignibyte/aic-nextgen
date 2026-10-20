<?php

declare(strict_types=1);

namespace App\Content;

use Psr\Http\Message\ServerRequestInterface;

use function is_string;
use function preg_match;

/**
 * Reads a content page's slug off a request (#50) and checks the shape of
 * one: lower-case words and digits joined by hyphens, up to 100 characters.
 */
final readonly class ContentRequest
{
    /**
     * @param ServerRequestInterface $serverRequest A request with the `slug` attribute.
     *
     * @return string The slug; empty when it is not one.
     */
    public function slug(ServerRequestInterface $serverRequest): string
    {
        $attribute = $serverRequest->getAttribute('slug');
        $slug = is_string($attribute)
            ? $attribute
            : '';

        return $this->isSlug($slug)
            ? $slug
            : '';
    }

    /**
     * @param string $slug A candidate slug.
     *
     * @return bool Whether it has a slug's shape.
     */
    public function isSlug(string $slug): bool
    {
        return preg_match('/^(?=.{1,100}$)[a-z0-9]+(-[a-z0-9]+)*$/', $slug) === 1;
    }
}
