<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Workflow\Application\Console\ConsoleArguments;
use AIC\Workflow\Application\Make\Source\ShapeSource;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Make\MadeFile;
use AIC\Workflow\Domain\Make\MadeShape;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_filter;
use function array_unique;
use function count;
use function explode;
use function implode;
use function in_array;
use function preg_match;
use function str_ends_with;

/**
 * A shape make's line read into its one file (#319). An enum takes
 * `--cases`, lower-case words joined by `_`, none twice, and `--backed`
 * (`string`, `int` or none); an exception's name ends in `Exception` and
 * `--extends` names an SPL root gate:8 lets a class extend; an interface's
 * name never ends in `Interface`.
 */
final readonly class MakeShapes
{
    /**
     * The exceptions a made one may extend: the SPL roots gate:8 lets a class extend (`noExtends`).
     */
    private const array PARENTS = ['InvalidArgumentException', 'LogicException', 'RuntimeException'];

    /**
     * @param MakeNames   $makeNames   The rules.
     * @param ShapeSource $shapeSource Writes the file.
     */
    public function __construct(
        private MakeNames $makeNames = new MakeNames(),
        private ShapeSource $shapeSource = new ShapeSource(),
    ) {
    }

    /**
     * @param MadeShape       $madeShape       The shape.
     * @param ParsedArguments $parsedArguments Its make's line.
     *
     * @throws WorkflowRefusal When a name or an option breaks a rule.
     *
     * @return MakeRecipe The file, in the shape's folder; no manifest call.
     */
    public function recipe(MadeShape $madeShape, ParsedArguments $parsedArguments): MakeRecipe
    {
        $suffix = $madeShape === MadeShape::Exception
            ? 'Exception'
            : '';
        [$module, $namespace, $name] = $this->makeNames->target($suffix, $parsedArguments);
        $source = match ($madeShape) {
            MadeShape::Enum => $this->enum($namespace, $name, $parsedArguments),
            MadeShape::Exception => $this->exception($namespace, $name, $parsedArguments),
            MadeShape::Interface => $this->contract($namespace, $name),
        };
        $path = 'modules/' . $module . '/src/' . $madeShape->folder() . '/' . $name . '.php';

        return new MakeRecipe($module, $name, [new MadeFile($path, $source)], null);
    }

    /**
     * @param string          $namespace       The module's namespace.
     * @param string          $name            The enum.
     * @param ParsedArguments $parsedArguments The line, for `--cases` and `--backed`.
     *
     * @throws WorkflowRefusal When a case or the backing breaks a rule.
     *
     * @return string The enum's bytes.
     */
    private function enum(string $namespace, string $name, ParsedArguments $parsedArguments): string
    {
        $backing = $parsedArguments->flag('backed');

        if (!in_array($backing, ['', 'string', 'int'], true)) {
            throw new WorkflowRefusal('--backed takes string or int; leave it out for a pure enum.', ConsoleArguments::USAGE);
        }

        $cases = $this->cases($parsedArguments->flag('cases'));

        return $this->shapeSource->enum($namespace, $name, $this->makeNames->words($name), $cases, $backing);
    }

    /**
     * @param string $cases What `--cases` gave.
     *
     * @throws WorkflowRefusal When there is none, one repeats, one is `class` or one is not lower-case words.
     *
     * @return list<string> The case words, in order.
     */
    private function cases(string $cases): array
    {
        $list = explode(',', $cases);
        $words = array_filter(
            $list,
            static fn (string $case): bool => preg_match('/^[a-z][a-z0-9]*(_[a-z0-9]+)*$/', $case) === 1 && $case !== 'class',
        );

        if (count($words) !== count($list) || count(array_unique($list)) !== count($list)) {
            throw new WorkflowRefusal(
                '--cases takes lower-case words joined by _, none twice and none `class`: --cases=open,on_hold.',
                ConsoleArguments::USAGE,
            );
        }

        return $list;
    }

    /**
     * @param string          $namespace       The module's namespace.
     * @param string          $name            The exception, ending in `Exception`.
     * @param ParsedArguments $parsedArguments The line, for `--extends`.
     *
     * @throws WorkflowRefusal When `--extends` names an exception gate:8 does not let a class extend.
     *
     * @return string The exception's bytes.
     */
    private function exception(string $namespace, string $name, ParsedArguments $parsedArguments): string
    {
        $parent = $parsedArguments->flag('extends');
        $parent = $parent === ''
            ? 'RuntimeException'
            : $parent;

        if (!in_array($parent, self::PARENTS, true)) {
            throw new WorkflowRefusal(
                '--extends takes ' . implode(', ', self::PARENTS) . '; RuntimeException when left out.',
                ConsoleArguments::USAGE,
            );
        }

        $words = $this->makeNames->words($this->makeNames->stem($name, 'Exception'));

        return $this->shapeSource->exception($namespace, $name, $words, $parent);
    }

    /**
     * @param string $namespace The module's namespace.
     * @param string $name      The interface.
     *
     * @throws WorkflowRefusal When the name ends in `Interface`.
     *
     * @return string The interface's bytes.
     */
    private function contract(string $namespace, string $name): string
    {
        if (str_ends_with($name, 'Interface')) {
            throw new WorkflowRefusal('the interface "' . $name . '" may not end in Interface: name what it is.', ConsoleArguments::USAGE);
        }

        return $this->shapeSource->contract($namespace, $name, $this->makeNames->words($name));
    }
}
