<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use function strtr;

/**
 * A module's manifest (`make:module`): its comment, the one `use`, and the chain with its requires (#122).
 */
final readonly class ManifestSource
{
    /**
     * The file, each `{name}` standing for the value of the parameter it names.
     */
    private const string TEMPLATE = <<<'PHP'
        <?php

        declare(strict_types=1);

        /*
         * The {module} module's manifest, made by bin/aic make:module (#122). The build
         * reads it; nothing reads it at run time.
         */

        use AIC\Module\Contracts\ModuleManifest;

        return (new ModuleManifest('{module}')){requires};
        PHP;

    /**
     * @param string $module   The module.
     * @param string $requires Its `->requires(...)` lines, each from its line break.
     *
     * @return string The file's bytes.
     */
    public function source(string $module, string $requires): string
    {
        return strtr(self::TEMPLATE, ['{module}' => $module, '{requires}' => $requires]) . "\n";
    }
}
