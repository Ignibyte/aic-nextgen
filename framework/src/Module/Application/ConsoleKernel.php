<?php

declare(strict_types=1);

namespace AIC\Module\Application;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\Job;
use AIC\Database\Contracts\Database;
use AIC\Notification\Contracts\MailTransport;
use AIC\Notification\Domain\MailConfig;
use AIC\Schedule\Domain\Schedule;

use function array_merge;
use function ksort;

/**
 * The app's console side, composed from the generated wiring (#93): the
 * framework's commands and jobs with the modules', the framework's migrations
 * before the modules', and every schedule. `bin/aic` runs its commands;
 * `aic-build` lists them in `build/surface.json`.
 */
final readonly class ConsoleKernel
{
    /**
     * The modules' commands and jobs are built from it.
     */
    private ConsoleContext $consoleContext;

    /**
     * @param Database      $database      The database.
     * @param MailTransport $mailTransport Sends mail.
     * @param MailConfig    $mailConfig    The mail settings.
     * @param ConsoleWiring $consoleWiring The modules' generated wiring.
     */
    public function __construct(
        private Database $database,
        private MailTransport $mailTransport,
        private MailConfig $mailConfig,
        private ConsoleWiring $consoleWiring,
    ) {
        $plugins = new Plugins($consoleWiring instanceof PluginWiring ? $consoleWiring : null);
        $this->consoleContext = new ConsoleContext($database, $mailTransport, $mailConfig, $plugins);
    }

    /**
     * @return array<string, Command> Every command, the modules' and the framework's, by name in name order.
     */
    public function commands(): array
    {
        $migrations = array_merge((new FrameworkMigrations())->all(), $this->consoleWiring->migrations());
        $frameworkCommands = (new FrameworkCommands($this->database))->byName($migrations, $this->jobs(), $this->schedules());
        $commands = array_merge($this->moduleCommands(), $frameworkCommands);
        ksort($commands);

        return $commands;
    }

    /**
     * @return array<string, Job> Every job, the modules' and the framework's, by name in name order.
     */
    public function jobs(): array
    {
        $frameworkJobs = new FrameworkJobs($this->database, $this->mailTransport, $this->mailConfig);
        $jobs = array_merge($this->moduleJobs(), $frameworkJobs->byName());
        ksort($jobs);

        return $jobs;
    }

    /**
     * @return list<Schedule> Every module's schedule.
     */
    public function schedules(): array
    {
        return $this->consoleWiring->schedules();
    }

    /**
     * An if per interface, not `match (true)`: elephc narrows `instanceof` in an
     * if, not in a match arm (#112).
     *
     * @return array<string, Command> The modules' commands by name; none when no module has one.
     */
    private function moduleCommands(): array
    {
        $consoleWiring = $this->consoleWiring;

        if ($consoleWiring instanceof CommandWiring) {
            return $consoleWiring->commands($this->consoleContext);
        }

        if ($consoleWiring instanceof RootlessCommandWiring) {
            return $consoleWiring->commands();
        }

        return [];
    }

    /**
     * @return array<string, Job> The modules' jobs by name; none when no module has one.
     */
    private function moduleJobs(): array
    {
        $consoleWiring = $this->consoleWiring;

        if ($consoleWiring instanceof JobWiring) {
            return $consoleWiring->jobs($this->consoleContext);
        }

        if ($consoleWiring instanceof RootlessJobWiring) {
            return $consoleWiring->jobs();
        }

        return [];
    }
}
