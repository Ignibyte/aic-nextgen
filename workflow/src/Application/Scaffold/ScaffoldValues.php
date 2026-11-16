<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Scaffold;

use AIC\Workflow\Application\Console\ConsoleArguments;
use AIC\Workflow\Application\Make\MakeNames;
use AIC\Workflow\Domain\Scaffold\ArgType;
use AIC\Workflow\Domain\Scaffold\ScaffoldArg;
use AIC\Workflow\Domain\Scaffold\ScaffoldManifest;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_all;
use function count;
use function explode;
use function implode;
use function in_array;
use function preg_match;

/**
 * The values a make fills a scaffold with (#298): `module`, its `namespace`,
 * the StudlyCaps `name`, then each argument from its `--<arg>=` or its
 * default, held to its type. A value that breaks its type is a usage refusal
 * (exit 2), so nothing is written. No type admits a quote, a backslash or a
 * path separator but cron's `/`, so a value cannot escape the text it fills.
 */
final readonly class ScaffoldValues
{
    /**
     * The placeholders every scaffold has, which no argument may take.
     */
    public const array BUILT_IN = ['module', 'name', 'namespace'];

    /**
     * @param MakeNames $makeNames The module and class rules every make shares.
     */
    public function __construct(
        private MakeNames $makeNames = new MakeNames(),
    ) {
    }

    /**
     * @param ScaffoldManifest      $scaffoldManifest The scaffold.
     * @param string                $module           The module.
     * @param string                $name             The name.
     * @param array<string, string> $flags            The line's flags, without dashes.
     *
     * @throws WorkflowRefusal With code 2 when a value breaks its rule or a required argument is missing.
     *
     * @return array<string, string> Every placeholder's value.
     */
    public function values(ScaffoldManifest $scaffoldManifest, string $module, string $name, array $flags): array
    {
        $values = [
            'module' => $module,
            'name' => $this->makeNames->class($name, ''),
            'namespace' => $this->makeNames->module($module),
        ];

        foreach ($scaffoldManifest->args as $scaffoldArg) {
            $value = $flags[$scaffoldArg->name] ?? $scaffoldArg->default
                ?? throw new WorkflowRefusal(
                    $scaffoldManifest->kind . ' needs --' . $scaffoldArg->name . '=<' . $scaffoldArg->argType->value . '>.',
                    ConsoleArguments::USAGE,
                );
            $values[$scaffoldArg->name] = $this->checked($scaffoldArg, $value);
        }

        return $values;
    }

    /**
     * @param ScaffoldArg $scaffoldArg An argument.
     * @param string      $value       A value for it.
     *
     * @throws WorkflowRefusal With code 2 when the value breaks the argument's type.
     *
     * @return string The value.
     */
    public function checked(ScaffoldArg $scaffoldArg, string $value): string
    {
        if ($this->fits($scaffoldArg, $value)) {
            return $value;
        }

        $what = $scaffoldArg->argType === ArgType::Choice
            ? 'one of ' . implode(', ', $scaffoldArg->choices)
            : self::rule($scaffoldArg->argType);

        throw new WorkflowRefusal('--' . $scaffoldArg->name . '="' . $value . '" must be ' . $what . '.', ConsoleArguments::USAGE);
    }

    /**
     * @param ScaffoldArg $scaffoldArg An argument.
     * @param string      $value       A value for it.
     *
     * @return bool Whether the value fits the argument's type.
     */
    private function fits(ScaffoldArg $scaffoldArg, string $value): bool
    {
        return match ($scaffoldArg->argType) {
            ArgType::Pascal => preg_match('/^[A-Z][A-Za-z0-9]*$/', $value) === 1,
            ArgType::Kebab => preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/', $value) === 1,
            ArgType::Cron => self::cron($value),
            ArgType::Choice => in_array($value, $scaffoldArg->choices, true),
            ArgType::Int => preg_match('/^-?(0|[1-9][0-9]{0,8})$/', $value) === 1,
        };
    }

    /**
     * @param string $value A value.
     *
     * @return bool Whether it is five cron fields, each of digits, `*`, `/`, `,` and `-`, one space apart.
     */
    private static function cron(string $value): bool
    {
        $fields = explode(' ', $value);

        return count($fields) === 5
            && array_all($fields, static fn (string $field): bool => preg_match('#^[0-9*/,-]+$#', $field) === 1);
    }

    /**
     * @param ArgType $argType A type other than a choice.
     *
     * @return string What a value of it must be.
     */
    private static function rule(ArgType $argType): string
    {
        return match ($argType) {
            ArgType::Pascal => 'StudlyCaps',
            ArgType::Kebab => 'lower case words joined by -',
            ArgType::Cron => 'five cron fields one space apart',
            ArgType::Choice => 'one of its choices',
            ArgType::Int => 'a whole number',
        };
    }
}
