<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use AIC\Workflow\Domain\Make\EntityPlan;

use function implode;
use function max;
use function str_pad;
use function strlen;
use function strtr;

/**
 * The list page `make:entity` writes (#123): GET of the table's path, a heading and the newest rows, one paragraph
 * each, in the framework's atoms; a template per entity would compile to the same markup for every entity.
 */
final readonly class ListPageSource
{
    /**
     * The file, each `{name}` standing for the value of the parameter it names.
     */
    private const string TEMPLATE = <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace {namespace}\Controller;

        use AIC\Component\Domain\Heading;
        use AIC\Component\Domain\Paragraph;
        use AIC\Controller\Application\PageDocuments;
        use AIC\Controller\Contracts\Page;
        use AIC\Controller\Contracts\PageResponse;
        use AIC\Database\Contracts\QueryException;
        {uses}
        use InvalidArgumentException;
        use Override;
        use Psr\Http\Message\ServerRequestInterface;

        use function array_map;

        /**
         * GET {path}, made by bin/aic make:entity (#123): the newest `{table}` rows,
         * a paragraph each, oldest first.
         */
        final readonly class {class}ListPage implements Page
        {
            /**
             * The most rows it shows.
             */
            private const int SHOWN = 50;

            /**
        {params}     */
            public function __construct(
                private PageDocuments $pageDocuments,
                private {class}Repository ${variable}Repository,
            ) {
            }

            /**
             * @param ServerRequestInterface $serverRequest The request; the page is the same for every one.
             *
             * @throws QueryException           When the rows cannot be read.
             * @throws InvalidArgumentException When the page layout is invalid.
             *
             * @return PageResponse The page.
             */
            #[Override]
            public function page(ServerRequestInterface $serverRequest): PageResponse
            {
                ${variable}Lines = array_map(
                    static fn ({class} ${variable}): Paragraph => new Paragraph(${variable}->line(), ''),
                    $this->{variable}Repository->latest(self::SHOWN),
                );

                return $this->pageDocuments->content([new Heading('{title}'), ...${variable}Lines], 200, '{title}');
            }
        }
        PHP;

    /**
     * @param EntityPlan $entityPlan The entity.
     *
     * @return string The file's bytes.
     */
    public function source(EntityPlan $entityPlan): string
    {
        $class = $entityPlan->class;
        $uses = [
            'use ' . $entityPlan->namespace . '\\Entity\\' . $class . ';',
            'use ' . $entityPlan->namespace . '\\Repository\\' . $class . 'Repository;',
        ];

        return strtr(self::TEMPLATE, [
            '{class}' => $class,
            '{namespace}' => $entityPlan->namespace,
            '{params}' => $this->params($class . 'Repository', '$' . $entityPlan->variable() . 'Repository'),
            '{path}' => $entityPlan->path(),
            '{table}' => $entityPlan->table,
            '{title}' => $entityPlan->title(),
            '{uses}' => implode("\n", $uses),
            '{variable}' => $entityPlan->variable(),
        ]) . "\n";
    }

    /**
     * @param string $repository The repository's class.
     * @param string $variable   Its parameter.
     *
     * @return string The constructor's two `@param` lines, aligned.
     */
    private function params(string $repository, string $variable): string
    {
        $type = max(strlen('PageDocuments'), strlen($repository));
        $name = max(strlen('$pageDocuments'), strlen($variable));

        return '     * @param ' . str_pad('PageDocuments', $type) . ' ' . str_pad('$pageDocuments', $name) . " Makes the page.\n"
            . '     * @param ' . str_pad($repository, $type) . ' ' . str_pad($variable, $name) . " Reads the rows.\n";
    }
}
