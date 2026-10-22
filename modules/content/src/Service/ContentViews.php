<?php

declare(strict_types=1);

namespace App\Content\Service;

use AIC\Component\Domain\Heading;
use AIC\Component\Domain\Paragraph;
use AIC\Controller\Domain\PageDocument;
use App\Content\Entity\ContentPage;
use App\Content\Form\ContentForm;
use App\Site\Service\HtmlLayout;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The content pages' shared documents: the editor, as the page that opens it
 * and the post that shows it again, and the 404 when there is no page.
 */
final readonly class ContentViews
{
    /**
     * @param HtmlLayout  $htmlLayout  Makes the documents.
     * @param ContentForm $contentForm The editor's form.
     */
    public function __construct(
        private HtmlLayout $htmlLayout,
        private ContentForm $contentForm,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     * @param ContentPage            $contentPage   The page as the editor shows it.
     * @param string                 $error         The error to show; empty for none.
     * @param int                    $status        200, or 422 for an editor shown again.
     *
     * @throws InvalidArgumentException When the status is neither.
     *
     * @return PageDocument The editor under its heading.
     */
    public function editor(
        ServerRequestInterface $serverRequest,
        ContentPage $contentPage,
        string $error,
        int $status,
    ): PageDocument {
        $heading = new Heading('Edit /pages/' . $contentPage->slug);

        return $this->htmlLayout->content([$heading, $this->contentForm->editor($serverRequest, $contentPage, $error)], $status);
    }

    /**
     * @return PageDocument The 404 for a slug with no page.
     */
    public function notFound(): PageDocument
    {
        return $this->htmlLayout->content([new Paragraph('There is no page here.', 'not-found')], 404);
    }
}
