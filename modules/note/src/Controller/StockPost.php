<?php

declare(strict_types=1);

namespace App\Note\Controller;

use AIC\Component\Domain\Heading;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use AIC\Database\Contracts\QueryException;
use AIC\Form\Application\PostedFields;
use App\Note\Form\StockEntry;
use App\Note\Form\StockForm;
use App\Note\Repository\StockRepository;
use App\Site\Service\HtmlLayout;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /stock (#65): adds the posted item and shows the list again (303), or
 * the form again with what is wrong (422).
 */
final readonly class StockPost implements Page
{
    /**
     * @param HtmlLayout      $htmlLayout      Makes the page.
     * @param StockRepository $stockRepository Holds the items.
     * @param StockForm       $stockForm       The form, shown again on a refusal.
     * @param PostedFields    $postedFields    Reads the posted fields.
     */
    public function __construct(
        private HtmlLayout $htmlLayout,
        private StockRepository $stockRepository,
        private StockForm $stockForm,
        private PostedFields $postedFields,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form, with its session.
     *
     * @throws QueryException When the item cannot be stored.
     * @throws LogicException When the required application state is unavailable.
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
        );
        $problem = $stockEntry->problem();

        if ($problem !== '') {
            $form = $this->stockForm->form($serverRequest, $stockEntry, $problem);

            return $this->htmlLayout->content([new Heading('Stock'), $form], 422);
        }

        $this->stockRepository->add($stockEntry);

        return new PageRedirect('/stock');
    }
}
