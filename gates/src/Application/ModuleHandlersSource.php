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

use function array_map;
use function count;
use function implode;
use function strtr;

/**
 * Writes `App\Generated\ModuleHandlers` (#92): the handler context built from
 * the composition root's objects, and every module's generated handlers merged
 * by id in build order, in chained parts (`ChainSource`).
 */
final readonly class ModuleHandlersSource
{
    /**
     * The most modules the root merges: its seven fixed types and the next part leave 7 of PHPMD's 15.
     */
    private const int ROOT_SIZE = 7;

    /**
     * The most modules a further part merges: its two fixed types and the next part leave 12.
     */
    private const int PART_SIZE = 12;

    /**
     * The root's fixed types besides the handler context's.
     */
    private const array ROOT_TYPES = [PageHandlers::class, ResponseWriter::class, AppConfig::class, Database::class, EnvelopeCodec::class];

    /**
     * The root, `{return}` standing for its return statement.
     */
    private const string ROOT_TEMPLATE = <<<'PHP'
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
     * A further part, `{class}` and `{return}` standing for its name and its return statement.
     */
    private const string PART_TEMPLATE = <<<'PHP'
        final readonly class {class}
        {
            /**
             * @param HandlerContext $handlerContext The roots and the kind wrappers.
             */
            public function __construct(
                private HandlerContext $handlerContext,
            ) {
            }

            /**
             * @return array<string, RequestHandlerInterface> These modules' handlers by id.
             */
            public function byId(): array
            {
                {return}
            }
        }
        PHP;

    /**
     * @param PhpClassFile $phpClassFile Lays out each file.
     * @param ChainSource  $chainSource  Splits the aggregator into parts.
     */
    public function __construct(
        private PhpClassFile $phpClassFile = new PhpClassFile(),
        private ChainSource $chainSource = new ChainSource(),
    ) {
    }

    /**
     * @param list<string> $handlerClasses The modules' `<M>Handlers` classes, in build order.
     *
     * @throws InvalidArgumentException When there are none: no module has a route.
     *
     * @return array<string, string> `ModuleHandlers` and its parts, by file name.
     */
    public function aggregate(array $handlerClasses): array
    {
        if ($handlerClasses === []) {
            throw new InvalidArgumentException('modules/ holds no module with a route; the generated wiring needs at least one (#92).');
        }

        $files = [];

        foreach ($this->chainSource->parts('ModuleHandlers', $handlerClasses, self::ROOT_SIZE, self::PART_SIZE) as $index => $part) {
            $files[$part['class'] . '.php'] = $index === 0
                ? $this->part(self::ROOT_TEMPLATE, $part, '$handlerContext', self::ROOT_TYPES)
                : $this->part(self::PART_TEMPLATE, $part, '$this->handlerContext', []);
        }

        return $files;
    }

    /**
     * @param string                                                    $template The root's or a part's template.
     * @param array{class: string, members: list<string>, next: string} $part     The part.
     * @param string                                                    $context  How it reaches the handler context.
     * @param list<string>                                              $types    Its fixed types besides the context's.
     *
     * @throws InvalidArgumentException Never: each part fits by its size.
     *
     * @return string The part's class file.
     */
    private function part(string $template, array $part, string $context, array $types): string
    {
        $members = $this->chainSource->calls($part);
        $calls = array_map(static fn (string $class): string => '(new ' . $class . '(' . $context . '))->byId()', $members);
        $return = count($calls) === 1
            ? 'return ' . $calls[0] . ';'
            : "return array_merge(\n            " . implode(",\n            ", $calls) . ",\n        );";
        $source = strtr($template, ['{class}' => $part['class'], '{return}' => $return]);
        $classes = [...$types, HandlerContext::class, RequestHandlerInterface::class, ...$this->chainSource->qualified($members)];
        $docblock = $this->phpClassFile->generated('the module manifests', "every module's handlers by id, merged into App\\AppHandlers.");

        return $this->phpClassFile->file('App\\Generated', $classes, count($calls) === 1 ? [] : ['array_merge'], $docblock, $source);
    }
}
