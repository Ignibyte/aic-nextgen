<?php

declare(strict_types=1);

namespace AIC\Profiler\Application;

use AIC\Component\Application\AtomTemplates;
use AIC\Component\Application\Renderer;
use AIC\Controller\Application\Psr7ResponseWriter;
use AIC\Database\Contracts\Database;
use AIC\Http\Application\FileResponses;
use AIC\Http\Application\ResponseBuilder;
use AIC\Http\Contracts\PublicAssets;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Puts the profiler's pages in front of the app (#230): their documents over
 * the database, and a writer whose Renderer knows the framework's atoms alone,
 * so the pages never need the app's theme.
 */
final readonly class ProfilerMount
{
    /**
     * @param RequestHandlerInterface $requestHandler  The app, which answers everything else.
     * @param Database                $database        The database the profiles are kept in.
     * @param ResponseBuilder         $responseBuilder Makes the responses.
     * @param PublicAssets            $publicAssets    The asset catalog, for the Tessera script.
     *
     * @return ProfilerPages The pages, then the app.
     */
    public function around(
        RequestHandlerInterface $requestHandler,
        Database $database,
        ResponseBuilder $responseBuilder,
        PublicAssets $publicAssets,
    ): ProfilerPages {
        $profileReader = new ProfileReader($database);

        return new ProfilerPages(
            $requestHandler,
            new ProfilerDocuments($profileReader, $publicAssets),
            new ProfilerFeed($profileReader),
            new Psr7ResponseWriter($responseBuilder, new Renderer(new AtomTemplates()), new FileResponses($responseBuilder)),
            $responseBuilder,
        );
    }
}
