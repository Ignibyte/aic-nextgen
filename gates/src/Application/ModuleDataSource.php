<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Module\Contracts\ModuleSchedule;
use AIC\Module\Contracts\ModuleSection;
use AIC\Schedule\Domain\Cadence;
use AIC\Schedule\Domain\Schedule;
use Gates\Domain\ModuleDefinition;
use InvalidArgumentException;

use function array_map;
use function count;
use function implode;
use function strlen;
use function strtr;
use function var_export;

/**
 * Writes a module's generated data classes (#93): `<M>Schedules`, its schedules
 * as the framework's `Schedule` values, and `<M>Prefixes`, its protected path
 * prefixes. Each holds only values, so it imports nothing of the module's.
 */
final readonly class ModuleDataSource
{
    /**
     * The widest line PHPCS allows.
     */
    private const int LINE_LIMIT = 140;

    /**
     * The most lines a list may take in the method (PHPCS's 20-line function limit).
     */
    private const int MAX_LINES = 20;

    /**
     * A class with `all()`: `{class}`, `{returnDoc}`, `{what}` and `{return}`.
     */
    private const string TEMPLATE = <<<'PHP'
        final readonly class {class}
        {
            /**
        {throws}     * @return {returnDoc} {what}
             */
            public function all(): array
            {
                {return}
            }
        }
        PHP;

    /**
     * @param PhpClassFile $phpClassFile Lays out the file.
     */
    public function __construct(
        private PhpClassFile $phpClassFile = new PhpClassFile(),
    ) {
    }

    /**
     * @param ModuleDefinition $moduleDefinition A module with schedules.
     *
     * @throws InvalidArgumentException When they do not fit one generated method.
     *
     * @return string Its `<M>Schedules` class file.
     */
    public function schedules(ModuleDefinition $moduleDefinition): string
    {
        $items = array_map($this->schedule(...), $moduleDefinition->declared(ModuleSchedule::class));
        $source = $this->source($moduleDefinition, 'Schedules', 'list<Schedule>', "The module's schedules.", $items);

        $docblock = $this->doc($moduleDefinition, 'its schedules.');

        $classes = [Schedule::class, Cadence::class, InvalidArgumentException::class];

        return $this->phpClassFile->file('App\\Generated', $classes, [], $docblock, $source);
    }

    /**
     * @param ModuleDefinition $moduleDefinition A module with protected prefixes.
     *
     * @throws InvalidArgumentException When they do not fit one generated method.
     *
     * @return string Its `<M>Prefixes` class file.
     */
    public function prefixes(ModuleDefinition $moduleDefinition): string
    {
        $prefixes = $moduleDefinition->entries(ModuleSection::ProtectedPrefixes);
        $items = array_map(static fn (string $prefix): string => var_export($prefix, true), $prefixes);
        $source = $this->source($moduleDefinition, 'Prefixes', 'list<string>', "The module's protected path prefixes.", $items);

        return $this->phpClassFile->file('App\\Generated', [], [], $this->doc($moduleDefinition, 'its protected path prefixes.'), $source);
    }

    /**
     * @param ModuleSchedule $moduleSchedule A schedule.
     *
     * @return string It as a `Schedule` expression.
     */
    private function schedule(ModuleSchedule $moduleSchedule): string
    {
        $payload = [];

        foreach ($moduleSchedule->payload as $key => $value) {
            $payload[] = var_export($key, true) . ' => ' . var_export($value, true);
        }

        return 'new Schedule(' . var_export($moduleSchedule->name, true) . ', ' . var_export($moduleSchedule->job, true)
            . ', [' . implode(', ', $payload) . '], new Cadence(' . var_export($moduleSchedule->cadence, true) . '))';
    }

    /**
     * @param ModuleDefinition $moduleDefinition The module.
     * @param string           $suffix           The class's suffix (`Schedules`).
     * @param string           $returnDoc        The return type in phpdoc.
     * @param string           $what             What it returns.
     * @param list<string>     $items            The list's items as code.
     *
     * @throws InvalidArgumentException When the list does not fit one generated method.
     *
     * @return string The class's declaration and body.
     */
    private function source(
        ModuleDefinition $moduleDefinition,
        string $suffix,
        string $returnDoc,
        string $what,
        array $items,
    ): string {
        $single = 'return [' . implode(', ', $items) . '];';
        $return = strlen('        ' . $single) <= self::LINE_LIMIT
            ? $single
            : "return [\n            " . implode(",\n            ", $items) . ",\n        ];";

        if (count($items) + 2 > self::MAX_LINES) {
            throw new InvalidArgumentException(
                'module ' . $moduleDefinition->name . ': its ' . $suffix . ' do not fit one generated method; split the module.',
            );
        }

        return strtr(self::TEMPLATE, [
            '{class}' => $moduleDefinition->studlyName() . $suffix,
            '{returnDoc}' => $returnDoc,
            '{return}' => $return,
            '{throws}' => $suffix === 'Schedules'
                ? "     * @throws InvalidArgumentException When a schedule cadence is invalid.\n     *\n"
                : '',
            '{what}' => $what,
        ]);
    }

    /**
     * @param ModuleDefinition $moduleDefinition The module.
     * @param string           $what             What the class holds.
     *
     * @return string Its class docblock.
     */
    private function doc(ModuleDefinition $moduleDefinition, string $what): string
    {
        return $this->phpClassFile->generated('modules/' . $moduleDefinition->name . '/' . $moduleDefinition->name . '.module.php', $what);
    }
}
