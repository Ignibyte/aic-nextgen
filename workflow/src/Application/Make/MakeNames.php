<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Workflow\Application\Console\ConsoleArguments;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_filter;
use function implode;
use function in_array;
use function preg_match;
use function preg_replace;
use function str_ends_with;
use function str_replace;
use function strlen;
use function strtolower;
use function substr;
use function ucwords;

/**
 * The names a make takes, held to the rules the build and the gates read
 * (#122), and what it derives from them. A name that breaks a rule is a
 * usage refusal (exit 2), so nothing is written.
 */
final readonly class MakeNames
{
    /**
     * The module names the build reserves.
     */
    private const array RESERVED = ['app', 'generated', 'module'];

    /**
     * The suffixes gate:26 reads as a kind, which a made class may not end in.
     */
    private const array KIND_SUFFIXES = ['Form', 'Repository', 'Template'];

    /**
     * @param string          $suffix          The suffix the class's kind needs; empty for none.
     * @param ParsedArguments $parsedArguments A make's line: the module, then the class.
     *
     * @throws WorkflowRefusal When either breaks a rule.
     *
     * @return array{string, string, string} The module, its namespace and the class.
     */
    public function target(string $suffix, ParsedArguments $parsedArguments): array
    {
        $module = $parsedArguments->positional(0);

        return [$module, $this->module($module), $this->class($parsedArguments->positional(1), $suffix)];
    }

    /**
     * @param string $module A module's name.
     *
     * @throws WorkflowRefusal When it breaks the build's rule or is reserved.
     *
     * @return string Its namespace, without the last `\`: `App\<Studly>`.
     */
    public function module(string $module): string
    {
        if (preg_match('/^[a-z][a-z0-9]*(_[a-z0-9]+)*$/', $module) !== 1 || in_array($module, self::RESERVED, true)) {
            throw $this->usage(
                'the module "' . $module . '" must be lower case words joined by _ (such as note_tags), and not app, generated or module',
            );
        }

        return 'App\\' . str_replace('_', '', ucwords($module, '_'));
    }

    /**
     * @param string $class  A class's short name.
     * @param string $suffix The suffix its kind needs; empty for none.
     *
     * @throws WorkflowRefusal When it is not StudlyCaps, lacks the suffix, or ends in a kind's suffix.
     *
     * @return string It.
     */
    public function class(string $class, string $suffix): string
    {
        $kind = array_filter(self::KIND_SUFFIXES, static fn (string $kind): bool => str_ends_with($class, $kind));

        return match (true) {
            preg_match('/^[A-Z][A-Za-z0-9]*$/', $class) !== 1 => throw $this->usage('the class "' . $class . '" must be StudlyCaps'),
            $suffix !== '' && !str_ends_with($class, $suffix) => throw $this->usage('the class "' . $class . '" must end in ' . $suffix),
            $kind !== [] => throw $this->usage('the class "' . $class . '" may not end in ' . implode(', ', self::KIND_SUFFIXES)),
            default => $class,
        };
    }

    /**
     * @param string $path A route's path.
     *
     * @throws WorkflowRefusal When it does not start with `/` or a segment is not a word or `{name}` / `{name:int}`.
     *
     * @return string It.
     */
    public function path(string $path): string
    {
        $segment = '([A-Za-z0-9._-]+|\\{[a-z][a-zA-Z0-9]*(:int)?\\})';

        return preg_match('#^(/|(/' . $segment . ')+)$#', $path) === 1
            ? $path
            : throw $this->usage('the path "' . $path . '" must start with / and hold words or {name} and {name:int} parameters');
    }

    /**
     * @param string $name A command's or a job's name.
     *
     * @throws WorkflowRefusal When it is not lower case words, `-` and `:`.
     *
     * @return string It.
     */
    public function name(string $name): string
    {
        return preg_match('/^[a-z][a-z0-9-]*(:[a-z][a-z0-9-]*)*$/', $name) === 1
            ? $name
            : throw $this->usage('the name "' . $name . '" must be lower case words joined by - and : (such as notes:prune)');
    }

    /**
     * @param string $value A table's name, or a region's.
     * @param string $what  `table` or `region`.
     *
     * @throws WorkflowRefusal When it is not lower case, with `_` in a table and `-` in a region.
     *
     * @return string It.
     */
    public function word(string $value, string $what): string
    {
        $pattern = $what === 'table'
            ? '/^[a-z][a-z0-9_]*$/'
            : '/^[a-z][a-z0-9-]*$/';

        return preg_match($pattern, $value) === 1
            ? $value
            : throw $this->usage('the ' . $what . ' "' . $value . '" must be lower case');
    }

    /**
     * @param string $weight A block's weight.
     *
     * @throws WorkflowRefusal When it is not a whole number.
     *
     * @return string It.
     */
    public function weight(string $weight): string
    {
        return preg_match('/^-?\\d+$/', $weight) === 1
            ? $weight
            : throw $this->usage('the weight "' . $weight . '" must be a whole number');
    }

    /**
     * @param string $version A migration's version.
     *
     * @throws WorkflowRefusal When it is not `YYYY_MM_DD_HHMMSS_<words>`.
     *
     * @return string It.
     */
    public function version(string $version): string
    {
        return preg_match('/^\\d{4}_\\d{2}_\\d{2}_\\d{6}_[a-z0-9_]+$/', $version) === 1
            ? $version
            : throw $this->usage('the version "' . $version . '" must be YYYY_MM_DD_HHMMSS_words');
    }

    /**
     * @param string $class A class's short name, such as `ScaffoldAboutPage`.
     *
     * @return string Its words, such as `Scaffold About Page`.
     */
    public function words(string $class): string
    {
        return (string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', ' ', $class);
    }

    /**
     * @param string $class A class's short name.
     *
     * @return string It in lower case joined by `-`, such as `scaffold-block`.
     */
    public function kebab(string $class): string
    {
        return strtolower(str_replace(' ', '-', $this->words($class)));
    }

    /**
     * @param string $class A class's short name.
     *
     * @return string It in lower case joined by `_`, such as `create_scaffold_items_table`.
     */
    public function snake(string $class): string
    {
        return strtolower(str_replace(' ', '_', $this->words($class)));
    }

    /**
     * @param string $class  A class's short name.
     * @param string $suffix The suffix to take off.
     *
     * @return string It without the suffix, such as `Scaffold` for `ScaffoldPage`.
     */
    public function stem(string $class, string $suffix): string
    {
        return $suffix !== '' && str_ends_with($class, $suffix) && $class !== $suffix
            ? substr($class, 0, -strlen($suffix))
            : $class;
    }

    /**
     * @param string $module    A module's name.
     * @param string $namespace Its namespace, without the last `\`.
     *
     * @return string Its line in composer.json's autoload.psr-4, as the build asks for it.
     */
    public function autoload(string $module, string $namespace): string
    {
        return '"' . str_replace('\\', '\\\\', $namespace . '\\') . '": "modules/' . $module . '/src/"';
    }

    /**
     * @param string $module    A module's name.
     * @param string $namespace Its namespace, without the last `\`.
     *
     * @return string Its root in phpcs.xml.dist, as the build asks for it.
     */
    public function phpcsRoot(string $module, string $namespace): string
    {
        return '<element key="modules/' . $module . '/src" value="' . $namespace . '"/>';
    }

    /**
     * @param string $why What is wrong.
     *
     * @return WorkflowRefusal The usage refusal (exit 2).
     */
    private function usage(string $why): WorkflowRefusal
    {
        return new WorkflowRefusal($why . '.', ConsoleArguments::USAGE);
    }
}
