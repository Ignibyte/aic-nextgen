<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\Job;
use AIC\Module\Contracts\ModuleCommand;
use AIC\Module\Contracts\ModuleJob;
use AIC\Module\Contracts\ModuleMiddleware;
use AIC\Module\Contracts\ModuleSection;
use Gates\Domain\ModuleDefinition;
use InvalidArgumentException;
use Psr\Http\Server\MiddlewareInterface;
use ReflectionClass;

use function class_exists;
use function is_a;
use function sprintf;
use function str_starts_with;

/**
 * The checks on what a manifest adds to the app's entry points and stack
 * (#93): each command and job is the module's own class of its kind; each
 * middleware is a PSR-15 middleware, the module's or the framework's; each
 * protected prefix is a path; each factory is the module's readonly class.
 */
final readonly class EntryChecks
{
    /**
     * @param ModuleDefinition $moduleDefinition The module.
     *
     * @throws InvalidArgumentException When a declaration fails its check.
     */
    public function check(ModuleDefinition $moduleDefinition): void
    {
        foreach ($moduleDefinition->declared(ModuleCommand::class) as $moduleCommand) {
            $this->checkClass($moduleDefinition, $moduleCommand->command, Command::class, false);
        }

        foreach ($moduleDefinition->declared(ModuleJob::class) as $moduleJob) {
            $this->checkClass($moduleDefinition, $moduleJob->job, Job::class, false);
        }

        foreach ($moduleDefinition->declared(ModuleMiddleware::class) as $moduleMiddleware) {
            $this->checkClass($moduleDefinition, $moduleMiddleware->middleware, MiddlewareInterface::class, true);
        }

        foreach ($moduleDefinition->entries(ModuleSection::Factories) as $factory) {
            $this->checkFactory($moduleDefinition, $factory);
        }

        $this->checkPrefixes($moduleDefinition);
    }

    /**
     * @param ModuleDefinition $moduleDefinition The module.
     * @param string           $factory          A declared factory.
     *
     * @throws InvalidArgumentException When it is not a readonly class of the module.
     */
    private function checkFactory(ModuleDefinition $moduleDefinition, string $factory): void
    {
        if (
            class_exists($factory)
            && $this->placed($moduleDefinition, $factory, false)
            && (new ReflectionClass($factory))->isReadOnly()
        ) {
            return;
        }

        $why = sprintf('factory %s must be a readonly class in %s', $factory, $moduleDefinition->namespace);

        throw $this->refusal($moduleDefinition, $why);
    }

    /**
     * @param ModuleDefinition $moduleDefinition The module.
     *
     * @throws InvalidArgumentException When a protected prefix is not a path on this site.
     */
    private function checkPrefixes(ModuleDefinition $moduleDefinition): void
    {
        foreach ($moduleDefinition->entries(ModuleSection::ProtectedPrefixes) as $prefix) {
            if (!str_starts_with($prefix, '/') || str_starts_with($prefix, '//')) {
                throw $this->refusal($moduleDefinition, sprintf('the protected prefix "%s" must be a path on this site', $prefix));
            }
        }
    }

    /**
     * @param ModuleDefinition $moduleDefinition The module.
     * @param string           $class            A declared command, job or middleware.
     * @param string           $type             What it must implement.
     * @param bool             $framework        Whether the framework's own class is allowed too.
     *
     * @throws InvalidArgumentException When it is not a class of the module (or the framework) implementing the type.
     */
    private function checkClass(ModuleDefinition $moduleDefinition, string $class, string $type, bool $framework): void
    {
        if ($this->placed($moduleDefinition, $class, $framework) && is_a($class, $type, true)) {
            return;
        }

        $where = $moduleDefinition->namespace . ($framework ? ' or the framework' : '');

        throw $this->refusal($moduleDefinition, sprintf('%s must be a class in %s implementing %s', $class, $where, $type));
    }

    /**
     * @param ModuleDefinition $moduleDefinition The module.
     * @param string           $class            A declared class.
     * @param bool             $framework        Whether the framework's own class counts.
     *
     * @return bool Whether it is an existing class of the module, or of the framework when that is allowed.
     */
    private function placed(ModuleDefinition $moduleDefinition, string $class, bool $framework): bool
    {
        $where = $moduleDefinition->owns($class) || ($framework && str_starts_with($class, 'AIC\\'));

        return $where && class_exists($class);
    }

    /**
     * @param ModuleDefinition $moduleDefinition The module.
     * @param string           $why              What is wrong.
     *
     * @return InvalidArgumentException The refusal, naming the module.
     */
    private function refusal(ModuleDefinition $moduleDefinition, string $why): InvalidArgumentException
    {
        return new InvalidArgumentException(sprintf('module %s: %s.', $moduleDefinition->name, $why));
    }
}
