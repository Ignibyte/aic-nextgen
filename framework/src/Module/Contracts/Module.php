<?php

declare(strict_types=1);

namespace AIC\Module\Contracts;

use AIC\Block\Contracts\Block;
use AIC\Schema\Contracts\Migration;

/**
 * A module's manifest (#90, #92): what `modules/<name>/` brings to the app.
 *
 * `modules/<name>/<name>.module.php` returns an anonymous class implementing
 * this. `bin/aic-build` reads every manifest and generates the wiring under
 * `app/Generated/`; nothing reads a manifest at run time, so the app compiles
 * natively. Each method returns declarations; an empty section returns `[]`.
 */
interface Module
{
    /**
     * @return string The module's name: its folder's name, lower case with underscores.
     */
    public function name(): string;

    /**
     * @return list<string> The names of the modules this one uses; they are wired first.
     */
    public function requires(): array;

    /**
     * @return list<ModuleRoute> The routes, each answered by a Page, Api or Stream in the module.
     */
    public function routes(): array;

    /**
     * @return list<Binding> The interfaces the module's classes implement for constructors to ask for.
     */
    public function services(): array;

    /**
     * @return list<class-string<Block>> The blocks, each with no required constructor parameter.
     */
    public function blocks(): array;

    /**
     * @return list<PluginTag> The plugins, each tagged under its type.
     */
    public function plugins(): array;

    /**
     * @return list<class-string<Migration>> The migrations under `src/Migration/`, oldest first.
     */
    public function migrations(): array;

    /**
     * @return list<class-string> The classes other modules may use: components, services, forms.
     */
    public function exports(): array;
}
