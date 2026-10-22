<?php

declare(strict_types=1);

namespace AIC\Module\Contracts;

/**
 * A module's manifest (#90, #92, #93): what `modules/<name>/` brings to the app.
 *
 * `modules/<name>/<name>.module.php` returns one, built from the module's name
 * with a wither per declaration, so it states only what the module has:
 *
 *     return (new ModuleManifest('faq'))
 *         ->route(RouteMethod::Get, '/faq', FaqPage::class)
 *         ->block(FaqNoteBlock::class);
 *
 * Each declaration is a `ModuleDeclaration`: a route, a binding, a plugin, a
 * command, a job, a schedule, a middleware, or a bare name by its section.
 * `bin/aic-build` reads every manifest and generates the wiring under
 * `generated/`; nothing reads a manifest at run time, so the app compiles
 * natively. Manifests build it; nothing in the framework does, so all of it is
 * API for them.
 *
 * @api
 */
final readonly class ModuleManifest
{
    /**
     * @param string                  $name         The module's name: its folder's name, lower case with underscores.
     * @param list<ModuleDeclaration> $declarations What it brings, in the order declared.
     */
    public function __construct(
        public string $name,
        public array $declarations = [],
    ) {
    }

    /**
     * @param string $module A module this one uses.
     *
     * @return self The manifest with it required.
     */
    public function requires(string $module): self
    {
        return $this->with(new ModuleEntry(ModuleSection::Requires, $module));
    }

    /**
     * @param RouteMethod  $routeMethod GET or POST.
     * @param string       $path        The path pattern (`/faq`, `/notes/{id:int}`).
     * @param class-string $handler     The Page, Api or Stream that answers it.
     *
     * @return self The manifest with the route.
     */
    public function route(RouteMethod $routeMethod, string $path, string $handler): self
    {
        return $this->with(new ModuleRoute($routeMethod, $path, $handler));
    }

    /**
     * @param class-string $interface The interface a constructor asks for.
     * @param class-string $class     The readonly class that implements it.
     *
     * @return self The manifest with the binding.
     */
    public function binding(string $interface, string $class): self
    {
        return $this->with(new Binding($interface, $class));
    }

    /**
     * @param class-string $block A block of the module.
     *
     * @return self The manifest with the block.
     */
    public function block(string $block): self
    {
        return $this->with(new ModuleEntry(ModuleSection::Blocks, $block));
    }

    /**
     * @param class-string $type  The plugin type, an interface.
     * @param class-string $class The plugin.
     *
     * @return self The manifest with the plugin.
     */
    public function plugin(string $type, string $class): self
    {
        return $this->with(new PluginTag($type, $class));
    }

    /**
     * @param class-string $migration A migration under `src/Migration/`.
     *
     * @return self The manifest with the migration, after the ones before it.
     */
    public function migration(string $migration): self
    {
        return $this->with(new ModuleEntry(ModuleSection::Migrations, $migration));
    }

    /**
     * @param class-string $class A class other modules may use.
     *
     * @return self The manifest with the export.
     */
    public function export(string $class): self
    {
        return $this->with(new ModuleEntry(ModuleSection::Exports, $class));
    }

    /**
     * @param string       $name    The command's name (`user:add`).
     * @param class-string $command The Command class.
     *
     * @return self The manifest with the command.
     */
    public function command(string $name, string $command): self
    {
        return $this->with(new ModuleCommand($name, $command));
    }

    /**
     * @param string       $name The job's name (`demo`).
     * @param class-string $job  The Job class.
     *
     * @return self The manifest with the job.
     */
    public function job(string $name, string $job): self
    {
        return $this->with(new ModuleJob($name, $job));
    }

    /**
     * @param string                $name    The schedule's name, unique in the app.
     * @param string                $job     The name of the job it queues.
     * @param array<string, string> $payload The payload the job is queued with.
     * @param string                $cadence A five-field UTC cron expression.
     *
     * @return self The manifest with the schedule.
     */
    public function schedule(string $name, string $job, array $payload, string $cadence): self
    {
        return $this->with(new ModuleSchedule($name, $job, $payload, $cadence));
    }

    /**
     * @param class-string       $middleware         A PSR-15 middleware class, the module's or the framework's.
     * @param MiddlewarePosition $middlewarePosition Where it runs.
     *
     * @return self The manifest with the middleware.
     */
    public function middleware(string $middleware, MiddlewarePosition $middlewarePosition): self
    {
        return $this->with(new ModuleMiddleware($middleware, $middlewarePosition));
    }

    /**
     * @param string $prefix A path prefix only a signed-in visitor may open.
     *
     * @return self The manifest with the prefix protected.
     */
    public function protectedPrefix(string $prefix): self
    {
        return $this->with(new ModuleEntry(ModuleSection::ProtectedPrefixes, $prefix));
    }

    /**
     * @param class-string $factory A readonly factory class of the module.
     *
     * @return self The manifest with the factory.
     */
    public function factory(string $factory): self
    {
        return $this->with(new ModuleEntry(ModuleSection::Factories, $factory));
    }

    /**
     * @param ModuleDeclaration $moduleDeclaration One more declaration.
     *
     * @return self The manifest with it, after the ones before it.
     */
    private function with(ModuleDeclaration $moduleDeclaration): self
    {
        return clone($this, ['declarations' => [...$this->declarations, $moduleDeclaration]]);
    }
}
