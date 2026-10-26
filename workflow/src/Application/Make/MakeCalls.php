<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Workflow\Domain\Make\ManifestCall;

/**
 * The manifest calls a make adds (#122). The routing enum's name is built
 * from its namespace, so the workflow names the framework only through the
 * Command kind.
 */
final readonly class MakeCalls
{
    /**
     * The namespace of the enum a route call names, `RouteMethod`.
     */
    private const string CONTRACTS = 'AIC\\Module\\Contracts';

    /**
     * @param string $method    `Get` or `Post`.
     * @param string $path      The route's path.
     * @param string $class     The controller.
     * @param string $namespace The module's namespace.
     *
     * @return ManifestCall `->route(RouteMethod::<method>, '<path>', <class>::class)`.
     */
    public function route(string $method, string $path, string $class, string $namespace): ManifestCall
    {
        return new ManifestCall(
            '->route(RouteMethod::' . $method . ", '" . $path . "', " . $class . '::class)',
            [self::CONTRACTS . '\\RouteMethod', $namespace . '\\Controller\\' . $class],
        );
    }

    /**
     * @param string $method    `block`, `export` or `migration`: a call that takes the class alone.
     * @param string $folder    The class's folder under `src/`.
     * @param string $class     The class.
     * @param string $namespace The module's namespace.
     *
     * @return ManifestCall `-><method>(<class>::class)`.
     */
    public function classOnly(string $method, string $folder, string $class, string $namespace): ManifestCall
    {
        return new ManifestCall('->' . $method . '(' . $class . '::class)', [$namespace . '\\' . $folder . '\\' . $class]);
    }

    /**
     * @param string $method    `command` or `job`: a call that takes a name and the class.
     * @param string $name      The name.
     * @param string $class     The class.
     * @param string $namespace The module's namespace.
     *
     * @return ManifestCall `-><method>('<name>', <class>::class)`.
     */
    public function named(string $method, string $name, string $class, string $namespace): ManifestCall
    {
        $folder = $method === 'job'
            ? 'Job'
            : 'Command';

        return new ManifestCall(
            '->' . $method . "('" . $name . "', " . $class . '::class)',
            [$namespace . '\\' . $folder . '\\' . $class],
        );
    }

    /**
     * @param ManifestCall $first  A call.
     * @param ManifestCall $second The call after it.
     *
     * @return ManifestCall Both, the second on the next line of the chain (`make:entity`, #123).
     */
    public function both(ManifestCall $first, ManifestCall $second): ManifestCall
    {
        return new ManifestCall($first->call . "\n    " . $second->call, [...$first->uses, ...$second->uses]);
    }
}
