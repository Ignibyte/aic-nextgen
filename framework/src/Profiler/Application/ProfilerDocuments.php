<?php

declare(strict_types=1);

namespace AIC\Profiler\Application;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Region;
use AIC\Component\Domain\TrackedAssets;
use AIC\Controller\Domain\PageDocument;
use AIC\Database\Contracts\QueryException;
use AIC\Http\Contracts\PublicAsset;
use AIC\Http\Contracts\PublicAssets;
use AIC\Profiler\Domain\ProfilerLayout;
use AIC\Profiler\Domain\ProfilerStyles;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;

use function strlen;
use function substr;

/**
 * Each profiler page as a document of the framework's own layout (#230): the
 * list, one profile, or a 404 for an id no profile has.
 */
final readonly class ProfilerDocuments
{
    /** The pages' prefix and the list's address. */
    public const string PATH = '/_profiler';

    /** The Tessera script's logical name in the asset catalog. */
    private const string TESSERA = 'tessera/tessera.csp.iife.js';

    /**
     * @param ProfileReader      $profileReader      Reads the kept profiles.
     * @param PublicAssets       $publicAssets       The asset catalog, for the Tessera script.
     * @param ProfilerQuery      $profilerQuery      Reads the pages' GET parameters.
     * @param ProfilerListView   $profilerListView   The list as components.
     * @param ProfilerDetailView $profilerDetailView One profile as components.
     */
    public function __construct(
        private ProfileReader $profileReader,
        private PublicAssets $publicAssets,
        private ProfilerQuery $profilerQuery = new ProfilerQuery(),
        private ProfilerListView $profilerListView = new ProfilerListView(),
        private ProfilerDetailView $profilerDetailView = new ProfilerDetailView(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     * @param string                 $path          Its path, under the prefix.
     *
     * @throws QueryException           When the profiles cannot be read.
     * @throws InvalidArgumentException When a component, key or status is invalid.
     *
     * @return PageDocument The page.
     */
    public function document(ServerRequestInterface $serverRequest, string $path): PageDocument
    {
        if ($path === self::PATH) {
            $profileFilter = $this->profilerQuery->filter($serverRequest);
            $content = $this->profilerListView->content($this->profileReader->rows($profileFilter), $profileFilter);

            return new PageDocument($this->layout('Requests', $content));
        }

        $profileView = $this->profileReader->view(substr($path, strlen(self::PATH . '/')));

        if ($profileView === null) {
            return new PageDocument($this->layout('Not found', $this->profilerListView->missing()), 404);
        }

        $title = $profileView->profileRow->method . ' ' . $profileView->profileRow->path;
        $content = $this->profilerDetailView->content($profileView, $this->profilerQuery->tab($serverRequest));

        return new PageDocument($this->layout($title, $content));
    }

    /**
     * @param string          $title   The page's title.
     * @param list<Component> $content Its content.
     *
     * @throws InvalidArgumentException When the region's name breaks the grammar.
     *
     * @return ProfilerLayout The page.
     */
    private function layout(string $title, array $content): ProfilerLayout
    {
        $tessera = $this->publicAssets->named(self::TESSERA);
        $script = $tessera instanceof PublicAsset
            ? $tessera->path
            : '';

        return new ProfilerLayout(
            $title . ' · AIC profiler',
            new Region(ProfilerLayout::CONTENT, $content),
            new TrackedAssets(ProfilerStyles::PATH, $script),
        );
    }
}
