<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\Scaffold\ScaffoldSource;
use AIC\Workflow\Domain\Scaffold\ScaffoldManifest;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;

use function array_unique;
use function array_values;
use function in_array;
use function sort;

/**
 * Every kind `bin/aic make` can find (#301): AIC's own, in the workflow
 * package's `scaffolds/`, and the app's, in its `scaffolds/`. A scaffold many
 * apps need moves into AIC by copying its folder into the package. A kind in
 * both is refused, so the app never shadows AIC's and AIC never shadows the
 * app's without anyone seeing it.
 */
final readonly class LayeredScaffolds implements ScaffoldSource
{
    /**
     * @param ScaffoldSource $core The workflow package's kinds.
     * @param ScaffoldSource $app  The app's kinds.
     */
    public function __construct(
        private ScaffoldSource $core,
        private ScaffoldSource $app,
    ) {
    }

    /**
     * @return list<string> Every kind of either, sorted, each once.
     */
    #[Override]
    public function kinds(): array
    {
        $kinds = array_values(array_unique([...$this->core->kinds(), ...$this->app->kinds()]));
        sort($kinds);

        return $kinds;
    }

    /**
     * @param string $kind A kind.
     *
     * @throws WorkflowRefusal When neither has it, both do, or its file returns anything but a ScaffoldManifest.
     *
     * @return ScaffoldManifest Its manifest, from the source that has it.
     */
    #[Override]
    public function manifest(string $kind): ScaffoldManifest
    {
        return $this->owner($kind)->manifest($kind);
    }

    /**
     * @param string $kind A kind.
     *
     * @throws WorkflowRefusal When both have it.
     *
     * @return string Its folder as shown, from the source that has it.
     */
    #[Override]
    public function folder(string $kind): string
    {
        return $this->owner($kind)->folder($kind);
    }

    /**
     * @param string $kind     A kind.
     * @param string $template A template's file name in its folder.
     *
     * @throws WorkflowRefusal When both have the kind.
     *
     * @return string|null The template's text, from the source that has the kind; null when there is no such file.
     */
    #[Override]
    public function template(string $kind, string $template): ?string
    {
        return $this->owner($kind)->template($kind, $template);
    }

    /**
     * @param string $kind A kind.
     *
     * @throws WorkflowRefusal When both have it.
     *
     * @return ScaffoldSource The source that has it; the app's when neither does, so its refusal names `scaffolds/`.
     */
    private function owner(string $kind): ScaffoldSource
    {
        $core = in_array($kind, $this->core->kinds(), true);

        if ($core && in_array($kind, $this->app->kinds(), true)) {
            throw new WorkflowRefusal(
                'scaffolds/' . $kind . "/ takes the name of an AIC kind in the workflow package; rename the app's folder and its kind.",
            );
        }

        return $core
            ? $this->core
            : $this->app;
    }
}
