<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Scaffold;

use AIC\Workflow\Application\Make\MakeCalls;
use AIC\Workflow\Application\Make\MakeNames;
use AIC\Workflow\Domain\Make\ManifestCall;
use AIC\Workflow\Domain\Scaffold\ScaffoldEdit;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_column;
use function array_filter;
use function array_key_exists;
use function array_map;
use function array_reduce;
use function array_slice;
use function array_values;
use function count;
use function in_array;
use function preg_match;
use function strrpos;
use function strtoupper;
use function substr;

/**
 * A scaffold's manifest edits (#298): their shapes, checked at load, and
 * the `ManifestCall` each becomes once its values are filled, chained like
 * `make:entity`'s, with the routes, commands and jobs it claims so the
 * planner refuses a collision. Every filled value is held to the rule the
 * built-in makes hold it to, so a call is always one the build reads.
 */
final readonly class ScaffoldEdits
{
    /**
     * How many values each method takes.
     */
    private const array ARITY = [
        'block' => 1,
        'command' => 2,
        'export' => 1,
        'job' => 2,
        'migration' => 1,
        'requires' => 1,
        'route' => 3,
        'schedule' => 3,
    ];

    /**
     * The namespace of the enum a route call names, `RouteMethod`.
     */
    private const string CONTRACTS = 'AIC\\Module\\Contracts';

    /**
     * @param ScaffoldTemplate $scaffoldTemplate Fills the values.
     * @param MakeNames        $makeNames        The rules for names, paths and modules.
     * @param MakeCalls        $makeCalls        Chains the calls.
     */
    public function __construct(
        private ScaffoldTemplate $scaffoldTemplate = new ScaffoldTemplate(),
        private MakeNames $makeNames = new MakeNames(),
        private MakeCalls $makeCalls = new MakeCalls(),
    ) {
    }

    /**
     * @param string       $where        The manifest's path, for the refusal.
     * @param ScaffoldEdit $scaffoldEdit An edit.
     *
     * @throws WorkflowRefusal When its method is not one the engine adds, it has the wrong number of values, or a
     *                         route's method is not Get or Post.
     */
    public function check(string $where, ScaffoldEdit $scaffoldEdit): void
    {
        $method = $scaffoldEdit->method;

        if (!array_key_exists($method, self::ARITY) || count($scaffoldEdit->values) !== self::ARITY[$method]) {
            throw new WorkflowRefusal($where . ': ->' . $method . '() is not an edit the engine adds with these values.');
        }

        if ($method === 'route' && !in_array(array_slice($scaffoldEdit->values, 0, 1), [['Get'], ['Post']], true)) {
            throw new WorkflowRefusal($where . ": ->route()'s method is Get or Post.");
        }
    }

    /**
     * @param list<ScaffoldEdit>    $edits    The scaffold's edits.
     * @param array<string, string> $values   Every placeholder's value.
     * @param list<string>          $requires The modules the manifest requires already, and the module itself.
     *
     * @throws WorkflowRefusal When a filled value breaks its rule.
     *
     * @return array{ManifestCall|null, list<string>} The calls chained (null for none) and what they claim.
     */
    public function calls(array $edits, array $values, array $requires): array
    {
        $made = [];

        foreach ($edits as $edit) {
            $filled = array_map(fn (string $value): string => $this->scaffoldTemplate->render($value, $values), $edit->values);
            $first = $filled[0] ?? '';

            if ($edit->method === 'requires' && in_array($first, $requires, true)) {
                continue;
            }

            $made[] = $this->call($edit->method, [$first, $filled[1] ?? '', $filled[2] ?? ''], $values['namespace'] ?? '');
        }

        $chained = array_reduce(
            array_column($made, 0),
            fn (?ManifestCall $carry, ManifestCall $manifestCall): ManifestCall => $carry instanceof ManifestCall
                ? $this->makeCalls->both($carry, $manifestCall)
                : $manifestCall,
        );

        return [$chained, array_values(array_filter(array_column($made, 1), static fn (string $claim): bool => $claim !== ''))];
    }

    /**
     * @param string                        $method    The manifest method.
     * @param array{string, string, string} $filled    Its values, filled, padded with empty strings.
     * @param string                        $namespace The module's namespace.
     *
     * @throws WorkflowRefusal When a value breaks its rule.
     *
     * @return array{ManifestCall, string} The call and what it claims; empty for nothing.
     */
    private function call(string $method, array $filled, string $namespace): array
    {
        [$first, $second, $third] = $filled;

        return match ($method) {
            'route' => $this->route($first, $second, $this->className($third), $namespace),
            'job', 'command' => [
                $this->classCall($method, "'" . $this->makeNames->name($first) . "', ", $second, $namespace),
                $method . ' ' . $first,
            ],
            'schedule' => [new ManifestCall($this->schedule($first, $second, $third), []), ''],
            'requires' => [new ManifestCall("->requires('" . $this->module($first) . "')", []), ''],
            default => [$this->classCall($method, '', $first, $namespace), ''],
        };
    }

    /**
     * @param string $method    `Get` or `Post`.
     * @param string $path      The route's path, filled.
     * @param string $class     The controller, relative to the module's namespace, checked.
     * @param string $namespace The module's namespace.
     *
     * @throws WorkflowRefusal When the path breaks its rule.
     *
     * @return array{ManifestCall, string} The route call and its claim.
     */
    private function route(string $method, string $path, string $class, string $namespace): array
    {
        $checked = $this->makeNames->path($path);
        $manifestCall = new ManifestCall(
            '->route(RouteMethod::' . $method . ", '" . $checked . "', " . self::short($class) . '::class)',
            [self::CONTRACTS . '\\RouteMethod', $namespace . '\\' . $class],
        );

        return [$manifestCall, 'route ' . strtoupper($method) . ' ' . $checked];
    }

    /**
     * @param string $method    The manifest method.
     * @param string $before    What the call takes before the class, such as `'notes:prune', `.
     * @param string $class     The class, relative to the module's namespace.
     * @param string $namespace The module's namespace.
     *
     * @throws WorkflowRefusal When the class breaks the rule.
     *
     * @return ManifestCall `-><method>(<before><Short>::class)`.
     */
    private function classCall(string $method, string $before, string $class, string $namespace): ManifestCall
    {
        $relative = $this->className($class);

        return new ManifestCall(
            '->' . $method . '(' . $before . self::short($relative) . '::class)',
            [$namespace . '\\' . $relative],
        );
    }

    /**
     * @param string $name    The schedule's name, filled.
     * @param string $job     The job's name, filled.
     * @param string $cadence The cron expression, filled.
     *
     * @throws WorkflowRefusal When a name or the cadence breaks its rule.
     *
     * @return string `->schedule('<name>', '<job>', [], '<cadence>')`.
     */
    private function schedule(string $name, string $job, string $cadence): string
    {
        if (preg_match('#^[0-9*/,-]+( [0-9*/,-]+){4}$#', $cadence) !== 1) {
            throw new WorkflowRefusal('the cadence "' . $cadence . '" must be five cron fields one space apart.');
        }

        return "->schedule('" . $this->makeNames->name($name) . "', '" . $this->makeNames->name($job) . "', [], '" . $cadence . "')";
    }

    /**
     * @param string $class A class relative to the module's namespace, filled.
     *
     * @throws WorkflowRefusal When it is not StudlyCaps segments joined by `\`.
     *
     * @return string It.
     */
    private function className(string $class): string
    {
        return preg_match('/^[A-Z][A-Za-z0-9]*(\\\\[A-Z][A-Za-z0-9]*)*$/', $class) === 1
            ? $class
            : throw new WorkflowRefusal('the class "' . $class . '" must be StudlyCaps names joined by \\, such as Job\\StockPoll.');
    }

    /**
     * @param string $module A module's name, filled.
     *
     * @throws WorkflowRefusal When it breaks the build's rule.
     *
     * @return string It.
     */
    private function module(string $module): string
    {
        $this->makeNames->module($module);

        return $module;
    }

    /**
     * @param string $class A class relative to the module's namespace.
     *
     * @return string Its short name.
     */
    private static function short(string $class): string
    {
        $last = strrpos($class, '\\');

        return $last === false
            ? $class
            : substr($class, $last + 1);
    }
}
