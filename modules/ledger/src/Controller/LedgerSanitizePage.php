<?php

declare(strict_types=1);

namespace App\Ledger\Controller;

use AIC\Component\Application\HtmlSanitizer;
use AIC\Component\Domain\CodeBlock;
use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Ledger\Form\SanitizeForm;
use App\Theme\Components\Prose\ProseBuilder;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;
use function is_array;
use function is_string;

/**
 * GET and POST /ledger/sanitize (#337): HTML a visitor wrote, from the form
 * or `?html=`, shown as `HtmlSanitizer` makes it, through the theme's Prose,
 * and as the markup it sends.
 */
final readonly class LedgerSanitizePage implements Page
{
    /**
     * @param PageDocuments $pageDocuments Makes the page.
     * @param HtmlSanitizer $htmlSanitizer Makes the markup safe.
     * @param SanitizeForm  $sanitizeForm  The form.
     * @param ProseBuilder  $proseBuilder  Shows the sanitized markup.
     */
    public function __construct(
        private PageDocuments $pageDocuments,
        private HtmlSanitizer $htmlSanitizer = new HtmlSanitizer(),
        private SanitizeForm $sanitizeForm = new SanitizeForm(),
        private ProseBuilder $proseBuilder = new ProseBuilder(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The GET, perhaps with `html`, or the form's POST.
     *
     * @throws LogicException When the request carries no session, or a constructed value is invalid.
     *
     * @return PageResponse The page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $body = $serverRequest->getParsedBody();
        $values = is_array($body)
            ? $body
            : $serverRequest->getQueryParams();
        $html = array_key_exists('html', $values) && is_string($values['html'])
            ? $values['html']
            : '';
        $sanitizedHtml = $this->htmlSanitizer->sanitize($html);

        return $this->pageDocuments->content([
            $this->sanitizeForm->form($serverRequest, $html),
            $this->proseBuilder->prose($sanitizedHtml),
            new CodeBlock($sanitizedHtml->markup),
        ], 200, 'Ledger sanitize');
    }
}
