<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\Wiki\WikiArchitecture;
use AIC\Workflow\Application\Wiki\WikiClasses;
use AIC\Workflow\Application\Wiki\WikiIndexPage;
use AIC\Workflow\Application\Wiki\WikiLinks;
use AIC\Workflow\Application\Wiki\WikiManifest;
use AIC\Workflow\Application\Wiki\WikiMarkdown;
use AIC\Workflow\Application\Wiki\WikiModulePage;
use AIC\Workflow\Application\Wiki\WikiPages;
use AIC\Workflow\Application\Wiki\WikiUsage;

/**
 * The code wiki's renderers, composed (#108), apart from `WikiKernel` so it
 * stays under PHPMD's coupling cap: the Markdown, the links read out of the
 * architecture pages, and the page sections.
 */
final readonly class WikiParts
{
    /**
     * @param string $root The project's root.
     */
    public function __construct(
        private string $root,
    ) {
    }

    /**
     * @param string $docs    The architecture pages' folder, relative to the root.
     * @param string $kinds   `kinds.md`'s text.
     * @param string $systems `systems.md`'s text.
     *
     * @return WikiPages The pages, linked into the architecture pages.
     */
    public function wikiPages(string $docs, string $kinds, string $systems): WikiPages
    {
        $wikiArchitecture = new WikiArchitecture();
        $wikiLinks = new WikiLinks($docs, $wikiArchitecture->kinds($kinds), $wikiArchitecture->systems($systems));
        $wikiMarkdown = new WikiMarkdown();
        $wikiClasses = new WikiClasses($wikiMarkdown, $wikiLinks);
        $wikiManifest = new WikiManifest($wikiMarkdown, $wikiLinks);
        $wikiUsage = new WikiUsage($wikiMarkdown, $wikiLinks);
        $wikiModulePage = new WikiModulePage($wikiMarkdown, $wikiLinks, $wikiManifest, $wikiClasses, $wikiUsage);
        $codeLookup = (new CodeParts($this->root))->codeLookup();

        return new WikiPages($codeLookup, $wikiModulePage, new WikiIndexPage($wikiMarkdown, $wikiLinks), $wikiClasses);
    }
}
