<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use function strtr;

/**
 * A component's declared `*.tpl.php`: its typing line and one paragraph of its title (#148).
 */
final readonly class ComponentTemplateSource
{
    /**
     * The file, each `{name}` standing for the value of the parameter it names.
     */
    private const string TEMPLATE = <<<'PHP'
        <?php /** @var \{namespace}\Component\{class} ${variable} */ ?>
        <p><?= ${variable}->title ?></p>
        PHP;

    /**
     * @param string $namespace The module's namespace.
     * @param string $class     The component.
     * @param string $variable  The variable its typing line names.
     *
     * @return string The file's bytes.
     */
    public function source(string $namespace, string $class, string $variable): string
    {
        return strtr(self::TEMPLATE, ['{namespace}' => $namespace, '{class}' => $class, '{variable}' => $variable]) . "\n";
    }
}
