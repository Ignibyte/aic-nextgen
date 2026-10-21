<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Config\Domain\AppConfig;
use AIC\Controller\Application\PageHandlers;
use AIC\Controller\Contracts\ResponseWriter;
use AIC\Database\Contracts\Database;
use AIC\Form\Application\EnvelopeCodec;
use AIC\Module\Application\HandlerContext;
use InvalidArgumentException;
use Psr\Http\Server\RequestHandlerInterface;

use function count;
use function implode;
use function sprintf;
use function strtr;

/**
 * Writes `App\Generated\ModuleHandlers` (#92): the handler context built from
 * the composition root's objects, and every module's generated handlers merged
 * by id, in build order.
 */
final readonly class ModuleHandlersSource
{
    /**
     * The most handler classes one `ModuleHandlers` merges (PHPMD's coupling limit of 15).
     */
    private const int MAX_MODULES = 8;

    /**
     * The class, `{return}` standing for its return statement.
     */
    private const string TEMPLATE = <<<'PHP'
        final readonly class ModuleHandlers
        {
            /**
             * @param PageHandlers   $pageHandlers   Wraps a Page with the response writer and the blocks.
             * @param ResponseWriter $responseWriter Writes the Api and Stream responses.
             * @param AppConfig      $appConfig      The app's settings.
             * @param Database       $database       The database.
             * @param EnvelopeCodec  $envelopeCodec  Seals and opens the forms' signed envelopes.
             * @param string         $bootedAt       When the app booted to serve this request, ISO 8601 in UTC.
             * @param string         $root           The app's root directory.
             */
            public function __construct(
                private PageHandlers $pageHandlers,
                private ResponseWriter $responseWriter,
                private AppConfig $appConfig,
                private Database $database,
                private EnvelopeCodec $envelopeCodec,
                private string $bootedAt,
                private string $root,
            ) {
            }

            /**
             * @return array<string, RequestHandlerInterface> The modules' handlers by id.
             */
            public function byId(): array
            {
                $handlerContext = new HandlerContext(
                    $this->pageHandlers,
                    $this->responseWriter,
                    $this->appConfig,
                    $this->database,
                    $this->envelopeCodec,
                    $this->bootedAt,
                    $this->root,
                );

                {return}
            }
        }
        PHP;

    /**
     * @param PhpClassFile $phpClassFile Lays out the file.
     */
    public function __construct(
        private PhpClassFile $phpClassFile = new PhpClassFile(),
    ) {
    }

    /**
     * @param list<string> $handlerClasses The generated handler classes' short names, in build order.
     *
     * @throws InvalidArgumentException When there are none, or more than one class may merge.
     *
     * @return string The class file.
     */
    public function source(array $handlerClasses): string
    {
        if ($handlerClasses === [] || count($handlerClasses) > self::MAX_MODULES) {
            throw new InvalidArgumentException(sprintf(
                '%d modules have routes; the generated ModuleHandlers merges from 1 to %d (#92).',
                count($handlerClasses),
                self::MAX_MODULES,
            ));
        }

        $classes = [PageHandlers::class, ResponseWriter::class, AppConfig::class, Database::class, EnvelopeCodec::class];
        $classes = [...$classes, HandlerContext::class, RequestHandlerInterface::class];

        return $this->phpClassFile->file(
            'App\\Generated',
            $classes,
            count($handlerClasses) === 1 ? [] : ['array_merge'],
            $this->phpClassFile->generated('the module manifests', "every module's handlers by id, merged into App\\AppHandlers."),
            strtr(self::TEMPLATE, ['{return}' => $this->returnStatement($handlerClasses)]),
        );
    }

    /**
     * @param non-empty-list<string> $handlerClasses The generated handler classes' short names, in build order.
     *
     * @return string `byId()`'s return: the one module's handlers, or every module's merged.
     */
    private function returnStatement(array $handlerClasses): string
    {
        $calls = [];

        foreach ($handlerClasses as $handlerClass) {
            $calls[] = '(new ' . $handlerClass . '($handlerContext))->byId()';
        }

        return count($calls) === 1
            ? 'return ' . $calls[0] . ';'
            : "return array_merge(\n            " . implode(",\n            ", $calls) . ",\n        );";
    }
}
