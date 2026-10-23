<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\CodexReadiness;
use AIC\Workflow\Domain\WorkflowClient;
use AIC\Workflow\Exception\WorkflowRefusal;
use JsonException;
use Override;
use stdClass;
use Throwable;

use function array_filter;
use function count;
use function in_array;
use function json_decode;
use function lcfirst;
use function preg_match;
use function realpath;

use const JSON_THROW_ON_ERROR;

/** Compare actual trusted native callbacks with this installed package before launch. */
final readonly class CodexReadyCheck implements CodexReadiness
{
    /** @param string $root The real project root. */
    public function __construct(private string $root)
    {
    }

    /** @return list<string> Actual readiness problems, empty only with all required trusted callbacks. */
    #[Override]
    public function problems(): array
    {
        try {
            $composerManifest = new ComposerManifest($this->root, __DIR__ . '/../..');
            $problem = $composerManifest->problem();

            if ($problem !== '') {
                throw new WorkflowRefusal($problem);
            }

            return $this->check($composerManifest->vendorDir());
        } catch (Throwable $throwable) {
            return ['Codex workflow launch is held: ' . $throwable->getMessage()];
        }
    }

    /**
     * @param string $vendor The actual vendor directory.
     *
     * @throws WorkflowRefusal When native configuration or responses do not satisfy readiness.
     * @throws JsonException   When package definitions are invalid.
     *
     * @return list<string> Unready native callbacks.
     */
    private function check(string $vendor): array
    {
        (new CodexPackCheck($this->root, $vendor))->check();
        $entries = (new CodexRpc($this->root, $this->root . '/' . $vendor . '/autoload.php'))->hooks()->objects('data');

        if (count($entries) !== 1) {
            throw new WorkflowRefusal('The native client did not return exactly one project.');
        }

        $entry = $entries[0];

        if (
            realpath($entry->string('cwd')) !== realpath($this->root)
            || $entry->objects('errors') !== []
            || $entry->strings('warnings') !== []
        ) {
            throw new WorkflowRefusal('The native project configuration has errors, warnings or a different root.');
        }

        return $this->callbacks($entry->objects('hooks'), $this->expected($vendor));
    }

    /**
     * @param string $vendor The actual vendor directory.
     *
     * @throws WorkflowRefusal When shipped callbacks are absent or invalid.
     * @throws JsonException   When shipped JSON is invalid.
     *
     * @return list<array{event:string,matcher:?string,command:string,timeout:int}> Shipped callback identities.
     */
    private function expected(string $vendor): array
    {
        $packManifest = new PackManifest(WorkflowClient::Codex);
        $text = (new PackageFiles(__DIR__ . '/../..', $vendor, $packManifest))->bytes($packManifest->hooks());
        $decoded = [json_decode(
            $text ?? throw new WorkflowRefusal('The native callback definitions are missing.'),
            false,
            64,
            JSON_THROW_ON_ERROR,
        )];
        $hooks = $decoded[0] instanceof stdClass
            ? (new JsonReader($decoded[0], 'shipped native hooks'))->object('hooks')
            : throw new WorkflowRefusal('The native callback definitions are invalid.');
        $expected = [];

        foreach ($hooks->keys() as $event) {
            $expected = [...$expected, ...$this->event($hooks->objects($event), $event)];
        }

        return $expected;
    }

    /**
     * @param list<JsonReader> $groups The shipped event groups.
     * @param string           $event  The native event.
     *
     * @throws WorkflowRefusal When a shipped field is invalid.
     *
     * @return list<array{event:string,matcher:?string,command:string,timeout:int}> Required callbacks.
     */
    private function event(array $groups, string $event): array
    {
        $expected = [];

        foreach ($groups as $group) {
            $matcher = in_array('matcher', $group->keys(), true)
                ? $group->string('matcher')
                : null;

            foreach ($group->objects('hooks') as $hook) {
                $expected[] = ['command' => $hook->string('command'),
                'event' => lcfirst($event),
                'matcher' => $matcher,
                'timeout' => $hook->int('timeout'),
                ];
            }
        }

        return $expected;
    }

    /**
     * @param list<JsonReader>                                                     $actual   Actual metadata returned by the native client.
     * @param list<array{event:string,matcher:?string,command:string,timeout:int}> $expected Shipped definitions.
     *
     * @throws WorkflowRefusal When native metadata is incomplete or invalid.
     *
     * @return list<string> Missing or untrusted callbacks.
     */
    private function callbacks(array $actual, array $expected): array
    {
        $problems = [];

        foreach ($expected as $definition) {
            $matches = array_filter($actual, fn (JsonReader $jsonReader): bool => $this->matches($jsonReader, $definition));

            if (count($matches) === 1) {
                continue;
            }

            $problems[] = $definition['event'] . ': review the current AIC callback with Codex /hooks before launch.';
        }

        return $problems;
    }

    /**
     * @param JsonReader                                                     $jsonReader An actual native hook.
     * @param array{event:string,matcher:?string,command:string,timeout:int} $definition Its required shipped identity.
     *
     * @throws WorkflowRefusal When native metadata is invalid.
     *
     * @return bool Whether this exact required callback is trusted and active.
     */
    private function matches(JsonReader $jsonReader, array $definition): bool
    {
        $actual = [
            'command' => $jsonReader->string('command'),
            'event' => $jsonReader->string('eventName'),
            'matcher' => $jsonReader->nullableString('matcher'),
            'source' => $jsonReader->string('sourcePath'),
            'timeout' => $jsonReader->int('timeoutSec'),
            'type' => $jsonReader->string('handlerType'),
        ];
        $expected = ['command' => $definition['command'],
        'event' => $definition['event'],
        'matcher' => $definition['matcher'],
        'source' => $this->root . '/.codex/hooks.json',
        'timeout' => $definition['timeout'],
        'type' => 'command',
        ];

        return $actual === $expected && $this->trusted($jsonReader);
    }

    /**
     * @param JsonReader $jsonReader Actual native trust metadata.
     *
     * @throws WorkflowRefusal When native trust metadata is invalid.
     *
     * @return bool Whether the exact current callback is enabled and trusted synchronously.
     */
    private function trusted(JsonReader $jsonReader): bool
    {
        return $jsonReader->bool('enabled') && !$jsonReader->bool('async') && $jsonReader->string('trustStatus') === 'trusted'
            && preg_match('/^sha256:[0-9a-f]{64}$/D', $jsonReader->string('currentHash')) === 1;
    }
}
