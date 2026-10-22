<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use function strtr;

/**
 * A block (`make:block`): one paragraph where its placement says (#122).
 */
final readonly class BlockSource
{
    /**
     * The file, each `{name}` standing for the value of the parameter it names.
     */
    private const string TEMPLATE = <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace {namespace}\Block;

        use AIC\Block\Contracts\Block;
        use AIC\Block\Contracts\Placement;
        use AIC\Component\Contracts\Component;
        use AIC\Component\Domain\Paragraph;
        use Override;
        use Psr\Http\Message\ServerRequestInterface;

        /**
         * {class}, made by bin/aic make:block (#122): a line in `{region}`, weight {weight}, {where}.
         */
        final readonly class {class} implements Block
        {
            /**
             * @return Placement Where it shows.
             */
            #[Override]
            public function placement(): Placement
            {
                return new Placement('{region}', {weight}, '{prefix}');
            }

            /**
             * @param ServerRequestInterface $serverRequest The page's request.
             *
             * @return Component Its line.
             */
            #[Override]
            public function component(ServerRequestInterface $serverRequest): Component
            {
                return new Paragraph('{title}', '{id}');
            }
        }
        PHP;

    /**
     * @param string $namespace The module's namespace.
     * @param string $class     The block.
     * @param string $region    Its region.
     * @param string $weight    Its weight.
     * @param string $prefix    Its route prefix; empty for every page.
     * @param string $title     Its line.
     * @param string $id        Its paragraph's id.
     *
     * @return string The file's bytes.
     */
    public function source(
        string $namespace,
        string $class,
        string $region,
        string $weight,
        string $prefix,
        string $title,
        string $id,
    ): string {
        return strtr(self::TEMPLATE, [
            '{class}' => $class,
            '{id}' => $id,
            '{namespace}' => $namespace,
            '{prefix}' => $prefix,
            '{region}' => $region,
            '{title}' => $title,
            '{weight}' => $weight,
            '{where}' => $prefix === '' ? 'on every page' : 'under `' . $prefix . '`',
        ]) . "\n";
    }
}
