<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Module\Contracts\ModuleCommand;
use AIC\Module\Contracts\ModuleJob;
use AIC\Module\Contracts\ModuleSchedule;
use AIC\Notification\Application\Notifier;
use AIC\Schedule\Domain\Cadence;
use Gates\Domain\ModuleDefinition;
use InvalidArgumentException;

use function array_fill_keys;
use function array_key_exists;
use function array_map;
use function sprintf;

/**
 * The checks across modules (#93): a command, job or schedule name is the
 * app's, so no two modules, and no module and the framework, may share one;
 * a schedule queues a job that exists, on a cadence that parses.
 */
final readonly class ModuleSetChecks
{
    /**
     * The framework's own command names (`AIC\Module\Application\FrameworkCommands`).
     */
    private const array FRAMEWORK_COMMANDS = ['migrate', 'migrate:status', 'queue:work', 'schedule:run'];

    /**
     * @param list<ModuleDefinition> $modules The modules.
     *
     * @throws InvalidArgumentException When a name is taken twice, or a schedule names no job or an invalid cadence.
     */
    public function check(array $modules): void
    {
        $this->claimAll($modules, 'command', self::FRAMEWORK_COMMANDS);
        $this->claimAll($modules, 'schedule', []);
        $jobs = $this->claimAll($modules, 'job', [Notifier::JOB]);

        foreach ($modules as $module) {
            $this->checkSchedules($module, $jobs);
        }
    }

    /**
     * @param list<ModuleDefinition> $modules   The modules.
     * @param string                 $what      `command`, `job` or `schedule`.
     * @param list<string>           $framework The framework's own names of that kind.
     *
     * @throws InvalidArgumentException When a name is empty or taken twice.
     *
     * @return array<string, string> Who holds each name.
     */
    private function claimAll(array $modules, string $what, array $framework): array
    {
        $taken = array_fill_keys($framework, 'the framework');

        foreach ($modules as $module) {
            foreach ($this->names($module, $what) as $name) {
                $taken = $this->claim($taken, $name, $module, $what);
            }
        }

        return $taken;
    }

    /**
     * @param ModuleDefinition $moduleDefinition A module.
     * @param string           $what             `command`, `job` or `schedule`.
     *
     * @return list<string> The names it declares of that kind.
     */
    private function names(ModuleDefinition $moduleDefinition, string $what): array
    {
        return match ($what) {
            'command' => array_map(
                static fn (ModuleCommand $moduleCommand): string => $moduleCommand->name,
                $moduleDefinition->declared(ModuleCommand::class),
            ),
            'job' => array_map(static fn (ModuleJob $moduleJob): string => $moduleJob->name, $moduleDefinition->declared(ModuleJob::class)),
            default => array_map(
                static fn (ModuleSchedule $moduleSchedule): string => $moduleSchedule->name,
                $moduleDefinition->declared(ModuleSchedule::class),
            ),
        };
    }

    /**
     * @param array<string, string> $taken            Who holds each name so far.
     * @param string                $name             A name a module declares.
     * @param ModuleDefinition      $moduleDefinition The module.
     * @param string                $what             What the name is of.
     *
     * @throws InvalidArgumentException When the name is taken.
     *
     * @return array<string, string> The names, this one held by the module.
     */
    private function claim(array $taken, string $name, ModuleDefinition $moduleDefinition, string $what): array
    {
        if ($name === '' || array_key_exists($name, $taken)) {
            $holder = $taken[$name] ?? 'nobody';

            $why = sprintf('the %s name "%s" is empty or already %s\'s', $what, $name, $holder);

            throw new InvalidArgumentException('module ' . $moduleDefinition->name . ': ' . $why . '.');
        }

        $taken[$name] = 'module ' . $moduleDefinition->name;

        return $taken;
    }

    /**
     * @param ModuleDefinition      $moduleDefinition A module.
     * @param array<string, string> $jobs             Every job name.
     *
     * @throws InvalidArgumentException When a schedule names no job or an invalid cadence.
     */
    private function checkSchedules(ModuleDefinition $moduleDefinition, array $jobs): void
    {
        foreach ($moduleDefinition->declared(ModuleSchedule::class) as $moduleSchedule) {
            if (!array_key_exists($moduleSchedule->job, $jobs) || !$this->parses($moduleSchedule->cadence)) {
                throw new InvalidArgumentException(sprintf(
                    'module %s: schedule %s must queue a job that exists ("%s") on a five-field cron cadence ("%s").',
                    $moduleDefinition->name,
                    $moduleSchedule->name,
                    $moduleSchedule->job,
                    $moduleSchedule->cadence,
                ));
            }
        }
    }

    /**
     * @param string $cadence A cron expression.
     *
     * @return bool Whether the framework's `Cadence` accepts it.
     */
    private function parses(string $cadence): bool
    {
        try {
            return (new Cadence($cadence))->expression === $cadence;
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}
