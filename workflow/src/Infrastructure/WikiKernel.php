<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\CodeAsk;
use AIC\Workflow\Application\CodeWikiCommand;
use AIC\Workflow\Application\Wiki\WikiPages;
use AIC\Workflow\Domain\CodeAnswer;
use AIC\Workflow\Domain\Console\SurfaceView;
use AIC\Workflow\Exception\WorkflowRefusal;

use function explode;
use function file_get_contents;
use function is_file;
use function str_contains;

/**
 * The code wiki, composed (#108): `composer run build`'s entry, which
 * rebuilds the code index in full so a hand-edited index cannot shape a
 * page, and `code:wiki`'s, which renders one page live. The architecture
 * pages it links are AIC's own (`framework/docs/` here, the installed
 * package's in an app); a missing one refuses.
 */
final readonly class WikiKernel
{
    /**
     * @param string $root The project's root.
     */
    public function __construct(
        private string $root,
    ) {
    }

    /**
     * @throws WorkflowRefusal When the surface is missing, the index cannot be built, or an architecture page is missing.
     *
     * @return array<string, string> Every page by its absolute path.
     */
    public function pages(): array
    {
        (new CodeParts($this->root))->codeIndex()->refresh(true);
        $pages = [];

        foreach ($this->wikiPages()->pages($this->view()) as $path => $markdown) {
            $pages[$this->root . '/' . $path] = $markdown;
        }

        return $pages;
    }

    /**
     * @param CodeAsk $codeAsk Refreshes the index, answers and records the lookup.
     *
     * @return CodeWikiCommand `bin/aic code:wiki`, rendering through this kernel.
     */
    public function command(CodeAsk $codeAsk): CodeWikiCommand
    {
        return new CodeWikiCommand($codeAsk, $this->answer(...));
    }

    /**
     * @param string $target A module, a class, or empty for the index.
     *
     * @throws WorkflowRefusal When nothing has that name, or the page cannot be rendered.
     *
     * @return CodeAnswer The page's lines, the module's files and its scope.
     */
    public function answer(string $target): CodeAnswer
    {
        $surfaceView = $this->view();
        $wikiPages = $this->wikiPages();

        if ($target === '') {
            return new CodeAnswer(explode("\n", $wikiPages->index($surfaceView)), [], ['*']);
        }

        $module = str_contains($target, '\\')
            ? $surfaceView->owner($target)
            : $target;
        $surfaceModule = $surfaceView->module($module)
            ?? throw new WorkflowRefusal('no module is named or holds ' . $target . '; bin/aic code:wiki lists them.');
        $files = (new CodeParts($this->root))->codeLookup()->filesUnder('modules/' . $module . '/');

        return new CodeAnswer(explode("\n", $wikiPages->module($surfaceModule, $surfaceView)), $files, ['module:' . $module]);
    }

    /**
     * @throws WorkflowRefusal When the surface is missing or not its shape.
     *
     * @return SurfaceView The surface the build wrote.
     */
    private function view(): SurfaceView
    {
        return (new SurfaceEntries($this->root, new LocalHost($this->root)))->view()
            ?? throw new WorkflowRefusal('build/surface.json is missing: run composer run build.');
    }

    /**
     * @throws WorkflowRefusal When an architecture page is missing.
     *
     * @return WikiPages The pages, linked into the architecture pages.
     */
    private function wikiPages(): WikiPages
    {
        $docs = is_file($this->root . '/framework/composer.json')
            ? 'framework/docs'
            : 'vendor/aic/framework/docs';
        $kinds = $this->read($docs . '/kinds.md');
        $systems = $this->read($docs . '/systems.md');
        $this->read($docs . '/README.md');

        return (new WikiParts($this->root))->wikiPages($docs, $kinds, $systems);
    }

    /**
     * @param string $path An architecture page, relative to the root.
     *
     * @throws WorkflowRefusal When it is missing.
     *
     * @return string Its text.
     */
    private function read(string $path): string
    {
        $file = $this->root . '/' . $path;

        return is_file($file)
            ? (string) file_get_contents($file)
            : throw new WorkflowRefusal($path . ' is missing: the wiki links into it.');
    }
}
