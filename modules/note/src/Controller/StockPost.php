<?php

declare(strict_types=1);

namespace App\Note\Controller;

use AIC\Component\Domain\Heading;
use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use AIC\Form\Application\PostedFields;
use App\Generated\ThemeLayoutName;
use App\Note\Form\StockEntry;
use App\Note\Form\StockForm;
use App\Note\Repository\StockRepository;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * POST /stock (#65): adds the posted item and shows the list again (303), or
 * the form again with what is wrong (422).
 */
final readonly class StockPost implements Page
{
    /**
     * @param PageDocuments   $pageDocuments   Makes the page.
     * @param StockRepository $stockRepository Holds the items.
     * @param StockForm       $stockForm       The form, shown again on a refusal.
     * @param PostedFields    $postedFields    Reads the posted fields.
     */
    public function __construct(
        private PageDocuments $pageDocuments,
        private StockRepository $stockRepository,
        private StockForm $stockForm,
        private PostedFields $postedFields,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form, with its session.
     *
     * @throws Throwable When the item cannot be stored, or the required application state is unavailable.
     *
     * @return PageResponse 303 to /stock, or the form again as 422.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $stockEntry = new StockEntry(
            $this->postedFields->value($serverRequest, 'name'),
            $this->postedFields->value($serverRequest, 'price'),
            $this->postedFields->value($serverRequest, 'added_on'),
            $this->postedFields->value($serverRequest, 'certified') === '1',
            $this->postedFields->value($serverRequest, 'sku'),
            $this->postedFields->value($serverRequest, 'quantity'),
        );
        $problem = $stockEntry->problem();

        if ($problem !== '') {
            $form = $this->stockForm->form($serverRequest, $stockEntry, $problem);

            return $this->pageDocuments->content([new Heading('Stock'), $form], 422, ThemeLayoutName::Focus);
        }

        $this->stockRepository->save($stockEntry);

        return new PageRedirect('/stock');
    }
}
