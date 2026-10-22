<?php

declare(strict_types=1);

namespace Gates\Domain;

use AIC\Block\Contracts\Block;
use AIC\Component\Contracts\Component;
use AIC\Controller\Contracts\Api;
use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\Job;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\Stream;
use AIC\Notification\Contracts\Notification;
use AIC\Schema\Contracts\Migration;
use Psr\Http\Server\MiddlewareInterface;

use function array_values;
use function class_exists;
use function in_array;
use function interface_exists;
use function is_a;
use function str_ends_with;

/**
 * A class's kind and the module folder it belongs in (#26, #94), the one map
 * the surface manifest and gate:26 share. A kind is read from the interface a
 * class implements (a class implements one; were it two, the first by
 * interface name would decide); Repository and Form have no contract,
 * so they are kinds by name suffix; a generated template class belongs beside
 * its component. Anything else is plain, and sits in `src/Entity/`,
 * `src/Service/` or `src/Form/`.
 */
final readonly class ClassKinds
{
    /**
     * The folders a plain class may sit in.
     */
    public const array PLAIN_FOLDERS = ['Entity', 'Service', 'Form'];

    /**
     * Every folder `src/` may hold.
     */
    public const array FOLDERS = [
        'Block',
        'Command',
        'Component',
        'Controller',
        'Entity',
        'Form',
        'Job',
        'Middleware',
        'Migration',
        'Notification',
        'Repository',
        'Service',
    ];

    /**
     * The kinds by interface, sorted by name: the kind's name, then its folder under `src/`.
     */
    private const array BY_INTERFACE = [
        Api::class => ['Api', 'Controller'],
        Block::class => ['Block', 'Block'],
        Command::class => ['Command', 'Command'],
        Component::class => ['Component', 'Component'],
        Job::class => ['Job', 'Job'],
        MiddlewareInterface::class => ['Middleware', 'Middleware'],
        Migration::class => ['Migration', 'Migration'],
        Notification::class => ['Notification', 'Notification'],
        Page::class => ['Page', 'Controller'],
        Stream::class => ['Stream', 'Controller'],
    ];

    /**
     * The kinds by class-name suffix, for the shapes with no contract.
     */
    private const array BY_SUFFIX = [
        'Form' => 'Form',
        'Repository' => 'Repository',
    ];

    /**
     * The entry kinds, which the surface manifest lists (#26).
     */
    private const array ENTRY_KINDS = ['Page', 'Api', 'Stream', 'Command', 'Job'];

    /**
     * @param string $class    A class name.
     * @param bool   $template Whether the build generated it from a template.
     *
     * @return array{string, string}|null Its kind and its folder under `src/`; null for a plain class.
     */
    public function kindOf(string $class, bool $template): ?array
    {
        if ($template) {
            return ['Template', 'Component'];
        }

        return $this->byInterface($class) ?? $this->bySuffix($class);
    }

    /**
     * @param string $class A class name.
     *
     * @return string Its entry kind (`Page`, `Api`, `Stream`, `Command`, `Job`), or `none`.
     */
    public function entryKind(string $class): string
    {
        $kind = $this->kindOf($class, false);

        return $kind !== null && in_array($kind[0], self::ENTRY_KINDS, true)
            ? $kind[0]
            : 'none';
    }

    /**
     * @param string $folder A folder under `src/`.
     *
     * @return list<string> The kinds it holds, for a message (`Page, Api and Stream`).
     */
    public function kindsIn(string $folder): array
    {
        $kinds = [];

        foreach ([...array_values(self::BY_INTERFACE), ...$this->suffixKinds()] as $kind) {
            if ($kind[1] !== $folder) {
                continue;
            }

            $kinds[] = $kind[0];
        }

        return $kinds;
    }

    /**
     * @param string $class A class name.
     *
     * @return array{string, string}|null The kind its first kind interface gives, if it implements one.
     */
    private function byInterface(string $class): ?array
    {
        if (!class_exists($class) && !interface_exists($class)) {
            return null;
        }

        foreach (self::BY_INTERFACE as $interface => $kind) {
            if (is_a($class, $interface, true)) {
                return $kind;
            }
        }

        return null;
    }

    /**
     * @param string $class A class name.
     *
     * @return array{string, string}|null The kind its name's suffix gives, for the kinds with no contract.
     */
    private function bySuffix(string $class): ?array
    {
        foreach (self::BY_SUFFIX as $suffix => $folder) {
            if (str_ends_with($class, $suffix)) {
                return [$suffix, $folder];
            }
        }

        return null;
    }

    /**
     * @return list<array{string, string}> The suffix kinds as kind and folder.
     */
    private function suffixKinds(): array
    {
        $kinds = [];

        foreach (self::BY_SUFFIX as $suffix => $folder) {
            $kinds[] = [$suffix, $folder];
        }

        return $kinds;
    }
}
