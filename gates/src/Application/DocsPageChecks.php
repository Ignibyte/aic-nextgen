<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Component\Application\Renderer;
use AIC\Form\Application\EnvelopeCodec;
use AIC\Form\Contracts\FormToken;
use AIC\Form\Domain\UploadKind;
use AIC\Tessera\Application\TesseraProps;
use AIC\Tessera\Domain\TesseraEvents;
use AIC\Tessera\Domain\TesseraRelease;
use AIC\Tessera\Domain\TesseraRequest;
use AIC\Workflow\Domain\Phase;
use Gates\Domain\MarkdownDocument;
use Gates\Domain\NativeProbeStatus;
use Gates\Domain\TemplateTypeKind;
use LogicException;

use function array_map;

/** The page-side and runtime documentation inventories, read from their actual source APIs. */
final readonly class DocsPageChecks
{
    /** @param DocsApiChecks $docsApiChecks Compares explicit inventory rows in the six required pages. */
    public function __construct(private DocsApiChecks $docsApiChecks = new DocsApiChecks())
    {
    }

    /**
     * @param array<string, MarkdownDocument> $pages    Every required page, validated before this call.
     * @param list<string>                    $commands The actual command names, supplied by the composition root.
     *
     * @throws LogicException When a required architecture page is absent.
     *
     * @return list<string> Missing or altered source inventories in the page-side and runtime topics.
     */
    public function check(array $pages, array $commands): array
    {
        return [
            ...$this->docsApiChecks->methods($this->page($pages, 'rendering.md'), Renderer::class),
            ...$this->docsApiChecks->cases($this->page($pages, 'templates.md'), TemplateTypeKind::class),
            ...$this->docsApiChecks->methods($this->page($pages, 'security.md'), EnvelopeCodec::class),
            ...$this->docsApiChecks->constants($this->page($pages, 'security.md'), FormToken::class),
            ...$this->docsApiChecks->cases($this->page($pages, 'security.md'), UploadKind::class),
            ...$this->tessera($this->page($pages, 'tessera.md')),
            ...$this->docsApiChecks->cases($this->page($pages, 'native.md'), NativeProbeStatus::class),
            ...$this->docsApiChecks->cases($this->page($pages, 'workflow.md'), Phase::class),
            ...$this->docsApiChecks->inventory($this->page($pages, 'workflow.md'), 'Command', array_map(
                static fn (string $name): string => 'Command::' . $name,
                $commands,
            )),
        ];
    }

    /**
     * @param MarkdownDocument $markdownDocument The required Tessera page.
     *
     * @return list<string> Missing or altered stream/props methods, protocol headers and release script.
     */
    private function tessera(MarkdownDocument $markdownDocument): array
    {
        return [
            ...$this->docsApiChecks->methods($markdownDocument, TesseraEvents::class),
            ...$this->docsApiChecks->methods($markdownDocument, TesseraProps::class),
            ...$this->docsApiChecks->constants($markdownDocument, TesseraRequest::class),
            ...$this->docsApiChecks->constants($markdownDocument, TesseraRelease::class),
        ];
    }

    /**
     * @param array<string, MarkdownDocument> $pages The required pages.
     * @param string                          $name  The page owning this inventory.
     *
     * @throws LogicException When required-page validation was bypassed.
     *
     * @return MarkdownDocument The checked required page.
     */
    private function page(array $pages, string $name): MarkdownDocument
    {
        return $pages[$name] ?? throw new LogicException('Required architecture page was not checked: ' . $name);
    }
}
