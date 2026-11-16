<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Scaffold;

/**
 * An app scaffold (#298): what `bin/aic make <kind>` writes. A scaffold is a
 * folder `scaffolds/<kind>/` whose `<kind>.scaffold.php` returns one, built
 * from the kind with a wither per part, beside the `*.tpl` templates it names:
 *
 *     return (new ScaffoldManifest('poller'))
 *         ->describe('A scheduled job that reads one upstream system.')
 *         ->notFor('An upstream that calls us: use a webhook.')
 *         ->from('job')
 *         ->arg('every', ArgType::Cron, '0 * * * *')
 *         ->file('src/Job/{name}Poll.php', 'job.php.tpl')
 *         ->job('{name|kebab}-poll', 'Job\{name}Poll')
 *         ->schedule('{name|kebab}-poll', '{name|kebab}-poll', '{every}')
 *         ->probe(['name' => 'Probe']);
 *
 * The engine fills the same templates with the same values every time, so the
 * same line gives the same bytes. The workflow's loader checks every part
 * before a make writes; nothing here decides anything.
 *
 * @api
 */
final readonly class ScaffoldManifest
{
    /**
     * @param string                $kind        Its kind: the folder's name, lower case joined by `-`.
     * @param string                $description What it makes, in one sentence.
     * @param list<string>          $notFor      When not to use it, each with what to use instead.
     * @param string                $from        The kind it was started from; empty when none.
     * @param list<ScaffoldArg>     $args        The arguments it takes beside the module and the name.
     * @param list<ScaffoldFile>    $files       The files it writes.
     * @param list<ScaffoldEdit>    $edits       The calls it adds to the module's manifest, in order.
     * @param array<string, string> $probe       The values a check makes it with: the name and any required argument.
     */
    public function __construct(
        public string $kind,
        public string $description = '',
        public array $notFor = [],
        public string $from = '',
        public array $args = [],
        public array $files = [],
        public array $edits = [],
        public array $probe = [],
    ) {
    }

    /**
     * @param string $description What it makes, in one sentence.
     *
     * @return self The manifest with it.
     */
    public function describe(string $description): self
    {
        return $this->with(description: $description);
    }

    /**
     * @param string $line When not to use it, and what to use instead.
     *
     * @return self The manifest with the line.
     */
    public function notFor(string $line): self
    {
        return $this->with(notFor: [...$this->notFor, $line]);
    }

    /**
     * @param string $kind The kind it was started from (`make:scaffold --from`, #299).
     *
     * @return self The manifest with it.
     */
    public function from(string $kind): self
    {
        return $this->with(from: $kind);
    }

    /**
     * @param string       $name    Its name, lower camel case.
     * @param ArgType      $argType What it may hold.
     * @param string|null  $default Its value when none is given; null when one must be.
     * @param list<string> $choices The values a choice allows.
     *
     * @return self The manifest with the argument.
     */
    public function arg(string $name, ArgType $argType, ?string $default = null, array $choices = []): self
    {
        return $this->with(args: [...$this->args, new ScaffoldArg($name, $argType, $default, $choices)]);
    }

    /**
     * @param string $path     Where, relative to `modules/<module>/`, with placeholders.
     * @param string $template The template beside the manifest.
     *
     * @return self The manifest with the file.
     */
    public function file(string $path, string $template): self
    {
        return $this->with(files: [...$this->files, new ScaffoldFile($path, $template)]);
    }

    /**
     * @param string $method `Get` or `Post`.
     * @param string $path   The route's path.
     * @param string $class  The Page, Api or Stream, relative to the module's namespace.
     *
     * @return self The manifest with the route.
     */
    public function route(string $method, string $path, string $class): self
    {
        return $this->withEdit('route', [$method, $path, $class]);
    }

    /**
     * @param string $name  The job's name.
     * @param string $class The job, relative to the module's namespace.
     *
     * @return self The manifest with the job.
     */
    public function job(string $name, string $class): self
    {
        return $this->withEdit('job', [$name, $class]);
    }

    /**
     * @param string $name    The schedule's name.
     * @param string $job     The job it queues, by name.
     * @param string $cadence Its cron expression.
     *
     * @return self The manifest with the schedule, whose payload is empty.
     */
    public function schedule(string $name, string $job, string $cadence): self
    {
        return $this->withEdit('schedule', [$name, $job, $cadence]);
    }

    /**
     * @param string $name  The command's name.
     * @param string $class The command, relative to the module's namespace.
     *
     * @return self The manifest with the command.
     */
    public function command(string $name, string $class): self
    {
        return $this->withEdit('command', [$name, $class]);
    }

    /**
     * @param string $class The block, relative to the module's namespace.
     *
     * @return self The manifest with the block.
     */
    public function block(string $class): self
    {
        return $this->withEdit('block', [$class]);
    }

    /**
     * @param string $class The migration, relative to the module's namespace.
     *
     * @return self The manifest with the migration.
     */
    public function migration(string $class): self
    {
        return $this->withEdit('migration', [$class]);
    }

    /**
     * @param string $class The class other modules may use, relative to the module's namespace.
     *
     * @return self The manifest with the export.
     */
    public function export(string $class): self
    {
        return $this->withEdit('export', [$class]);
    }

    /**
     * @param string $module A module the made files use; added only when the manifest lacks it.
     *
     * @return self The manifest with the requirement.
     */
    public function requires(string $module): self
    {
        return $this->withEdit('requires', [$module]);
    }

    /**
     * @param array<string, string> $values The name and every required argument, as a check makes it.
     *
     * @return self The manifest with them.
     */
    public function probe(array $values): self
    {
        return $this->with(probe: $values);
    }

    /**
     * @param string       $method The manifest method.
     * @param list<string> $values Its values.
     *
     * @return self The manifest with the edit.
     */
    private function withEdit(string $method, array $values): self
    {
        return $this->with(edits: [...$this->edits, new ScaffoldEdit($method, $values)]);
    }

    /**
     * @param string|null                $description What it makes.
     * @param list<string>|null          $notFor      When not to use it.
     * @param string|null                $from        The kind it started from.
     * @param list<ScaffoldArg>|null     $args        Its arguments.
     * @param list<ScaffoldFile>|null    $files       Its files.
     * @param list<ScaffoldEdit>|null    $edits       Its manifest calls.
     * @param array<string, string>|null $probe       Its probe values.
     *
     * @return self A copy with each part given, the rest kept.
     */
    private function with(
        ?string $description = null,
        ?array $notFor = null,
        ?string $from = null,
        ?array $args = null,
        ?array $files = null,
        ?array $edits = null,
        ?array $probe = null,
    ): self {
        return new self(
            $this->kind,
            $description ?? $this->description,
            $notFor ?? $this->notFor,
            $from ?? $this->from,
            $args ?? $this->args,
            $files ?? $this->files,
            $edits ?? $this->edits,
            $probe ?? $this->probe,
        );
    }
}
