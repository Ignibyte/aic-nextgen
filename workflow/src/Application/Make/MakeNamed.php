<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Make\MadeFile;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Exception\WorkflowRefusal;
use Closure;

use function ucfirst;

/**
 * The recipe `make:command` and `make:job` share (#122): a class in the
 * kind's folder under a name the manifest declares with it.
 */
final readonly class MakeNamed
{
    /**
     * @param MakeNames $makeNames The rules.
     * @param MakeCalls $makeCalls The manifest call.
     */
    public function __construct(
        private MakeNames $makeNames = new MakeNames(),
        private MakeCalls $makeCalls = new MakeCalls(),
    ) {
    }

    /**
     * @param ParsedArguments                         $parsedArguments The line: the module, the class, the name.
     * @param string                                  $kind            `command` or `job`.
     * @param Closure(string, string, string): string $source          Writes the class from its namespace, class and name.
     *
     * @throws WorkflowRefusal When a name breaks a rule.
     *
     * @return MakeRecipe The class and its manifest call.
     */
    public function recipe(ParsedArguments $parsedArguments, string $kind, Closure $source): MakeRecipe
    {
        [$module, $namespace, $class] = $this->makeNames->target('', $parsedArguments);
        $name = $this->makeNames->name($parsedArguments->positional(2));
        $path = 'modules/' . $module . '/src/' . ucfirst($kind) . '/' . $class . '.php';
        $madeFile = new MadeFile($path, $source($namespace, $class, $name));
        $manifestCall = $this->makeCalls->named($kind, $name, $class, $namespace);

        return new MakeRecipe($module, $name, [$madeFile], $manifestCall, [$kind . ' ' . $name]);
    }
}
