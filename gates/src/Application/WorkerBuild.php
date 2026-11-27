<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Controller\Contracts\Job;
use AIC\Database\Contracts\Database;
use AIC\Queue\Application\PostgresQueue;
use AIC\Storage\Application\PrivateFiles;
use Gates\Domain\WorkerJob;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;

use function array_keys;
use function array_map;
use function basename;
use function class_exists;
use function glob;
use function is_a;
use function lcfirst;
use function mb_strtolower;
use function preg_replace;
use function sort;
use function sprintf;
use function str_ends_with;
use function str_replace;
use function substr;
use function ucwords;

/**
 * The build's wiring of each interpreted worker (#336): every class in a
 * worker's `src/Job/` that implements Job, named by its class (`StatementJob`
 * → `statement`) and built from the database and the app's root, written to
 * the worker's own `src/Generated/<Name>Jobs.php`, which the build owns. A
 * worker installs on its own, so it never reads `build/generated/`.
 */
final readonly class WorkerBuild
{
    /** What a worker's job may take, each as PHP over `$database` and `$root`. */
    private const array SERVICES = [
        Database::class => '$database',
        PostgresQueue::class => 'new PostgresQueue($database)',
        PrivateFiles::class => 'new PrivateFiles($root)',
    ];

    /**
     * @param string           $root             The project's root.
     * @param ProjectProfile   $projectProfile   Finds the workers.
     * @param WorkerJobsSource $workerJobsSource Writes each worker's wiring.
     */
    public function __construct(
        private string $root,
        private ProjectProfile $projectProfile = new ProjectProfile(),
        private WorkerJobsSource $workerJobsSource = new WorkerJobsSource(),
    ) {
    }

    /**
     * @throws InvalidArgumentException When a job does not load or asks for what a worker cannot give.
     *
     * @return array<string, string> Each worker's wiring by absolute path, for each worker with a job.
     */
    public function files(): array
    {
        $files = [];

        foreach ($this->projectProfile->workers($this->root) as $worker) {
            $studly = str_replace(' ', '', ucwords(str_replace('_', ' ', basename($worker))));
            $jobs = $this->jobs($worker, 'Worker\\' . $studly);

            if ($jobs === []) {
                continue;
            }

            $files[$this->generated($worker) . '/' . $studly . 'Jobs.php'] = $this->workerJobsSource->file(
                $worker,
                'Worker\\' . $studly,
                $studly,
                $jobs,
            );
        }

        return $files;
    }

    /**
     * @return list<string> Each worker's generated folder, absolute, which the build owns.
     */
    public function folders(): array
    {
        $folders = [];

        foreach ($this->projectProfile->workers($this->root) as $worker) {
            $folders[] = $this->generated($worker);
        }

        return $folders;
    }

    /**
     * @param string $worker A worker's folder.
     *
     * @return string Its generated folder, absolute.
     */
    private function generated(string $worker): string
    {
        return $this->root . '/' . $worker . '/src/Generated';
    }

    /**
     * @param string $worker    The worker's folder.
     * @param string $namespace Its namespace.
     *
     * @throws InvalidArgumentException When a job does not load or asks for what a worker cannot give.
     *
     * @return list<WorkerJob> Its jobs, sorted by file.
     */
    private function jobs(string $worker, string $namespace): array
    {
        $files = glob($this->root . '/' . $worker . '/src/Job/*.php');
        $files = $files === false
            ? []
            : $files;
        sort($files);
        $jobs = [];

        foreach ($files as $file) {
            $class = $this->loaded($worker, $namespace, basename($file, '.php'));

            if (!is_a($class, Job::class, true)) {
                continue;
            }

            $jobs[] = $this->job($worker, $class);
        }

        return $jobs;
    }

    /**
     * @param string $worker    The worker's folder.
     * @param string $namespace Its namespace.
     * @param string $short     A class's short name in its `src/Job/`.
     *
     * @throws InvalidArgumentException When it does not load: the root's autoloader lacks the worker.
     *
     * @return class-string The class.
     */
    private function loaded(string $worker, string $namespace, string $short): string
    {
        $class = $namespace . '\\Job\\' . $short;

        if (class_exists($class)) {
            return $class;
        }

        throw new InvalidArgumentException(sprintf(
            '%s: %s does not load; add "%s\\\\": "%s/src/" to composer.json\'s autoload-dev.psr-4 and run composer dump-autoload.',
            $worker,
            $class,
            str_replace('\\', '\\\\', $namespace),
            $worker,
        ));
    }

    /**
     * @param string            $worker The worker's folder.
     * @param class-string<Job> $class  A job class.
     *
     * @throws InvalidArgumentException When its constructor asks for what a worker cannot give.
     *
     * @return WorkerJob The job, wired.
     */
    private function job(string $worker, string $class): WorkerJob
    {
        $constructor = (new ReflectionClass($class))->getConstructor();

        if ($constructor === null) {
            return new WorkerJob($this->name($class), $class, [], []);
        }

        $uses = [];

        foreach ($constructor->getParameters() as $reflectionParameter) {
            if ($reflectionParameter->isOptional()) {
                break;
            }

            $uses[] = $this->service($worker, $class, $reflectionParameter);
        }

        $arguments = array_map(static fn (string $use): string => self::SERVICES[$use], $uses);

        return new WorkerJob($this->name($class), $class, $uses, $arguments);
    }

    /**
     * @param string              $worker              The worker's folder.
     * @param string              $class               The job class.
     * @param ReflectionParameter $reflectionParameter One of its constructor's required parameters.
     *
     * @throws InvalidArgumentException When it asks for what a worker cannot give.
     *
     * @return key-of<self::SERVICES> The class it takes.
     */
    private function service(string $worker, string $class, ReflectionParameter $reflectionParameter): string
    {
        $type = $reflectionParameter->getType();
        $name = $type instanceof ReflectionNamedType && !$type->allowsNull()
            ? $type->getName()
            : '';

        foreach (array_keys(self::SERVICES) as $service) {
            if ($service === $name) {
                return $service;
            }
        }

        throw new InvalidArgumentException(sprintf(
            '%s: %s takes $%s, but a worker\'s job takes only Database, PostgresQueue and PrivateFiles.',
            $worker,
            $class,
            $reflectionParameter->getName(),
        ));
    }

    /**
     * @param string $class A job class.
     *
     * @return string Its queue name: the short name without `Job`, in kebab case.
     */
    private function name(string $class): string
    {
        $short = basename(str_replace('\\', '/', $class));
        $short = str_ends_with($short, 'Job')
            ? substr($short, 0, -3)
            : $short;

        return mb_strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', lcfirst($short)) ?? $short);
    }
}
