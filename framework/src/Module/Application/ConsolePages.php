<?php

declare(strict_types=1);

namespace AIC\Module\Application;

use AIC\Component\Application\Renderer;
use AIC\Config\Domain\AppConfig;
use AIC\Container\Exception\ContainerException;
use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Application\Psr7ResponseWriter;
use AIC\Http\Application\FileResponses;
use AIC\Http\Application\ResponseBuilder;
use AIC\Http\Application\ResponseFactory;
use AIC\Http\Application\StreamFactory;
use AIC\Storage\Application\PageFiles;
use AIC\Storage\Application\PrivateFiles;
use InvalidArgumentException;

/**
 * The console's pages (#265): what a command or a job needs to make a page in the app's theme and
 * keep it, which the web side has from its request handling. It reads the theme from the generated
 * wiring each time it is asked, so a console run that makes no page builds no presentation.
 */
final readonly class ConsolePages
{
    /**
     * @param ConsoleWiring $consoleWiring The modules' generated wiring, with the theme's presentation.
     * @param AppConfig     $appConfig     The app's settings, for its name in a page's title.
     * @param string        $root          The app's root directory, which the private files are under.
     */
    public function __construct(
        private ConsoleWiring $consoleWiring,
        private AppConfig $appConfig,
        private string $root,
    ) {
    }

    /**
     * @throws InvalidArgumentException When the compiled asset metadata is invalid.
     * @throws ContainerException       When the generated presentation declares no theme.
     *
     * @return PageDocuments A page's documents in the theme's layouts.
     */
    public function documents(): PageDocuments
    {
        return new PageDocuments(
            $this->appConfig,
            $this->consoleWiring->presentation()->pageLayouts
                ?? throw new ContainerException('The generated presentation declares no theme.'),
        );
    }

    /**
     * @throws InvalidArgumentException When the compiled asset metadata is invalid.
     *
     * @return PageFiles Keeps rendered pages as private files.
     */
    public function pageFiles(): PageFiles
    {
        $streamFactory = new StreamFactory();
        $responseBuilder = new ResponseBuilder(new ResponseFactory($streamFactory), $streamFactory);
        $renderer = new Renderer($this->consoleWiring->presentation()->componentTemplates);

        return new PageFiles(
            new PrivateFiles($this->root),
            new Psr7ResponseWriter($responseBuilder, $renderer, new FileResponses($responseBuilder)),
        );
    }
}
