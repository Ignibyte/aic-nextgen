<?php

declare(strict_types=1);

namespace App\Stock;

use AIC\Component\Contracts\Html;
use AIC\Component\Domain\Alert;
use AIC\Component\Domain\AlertSeverity;
use AIC\Component\Domain\Button;
use AIC\Component\Domain\Checkbox;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\FormField;
use AIC\Component\Domain\Input;
use AIC\Form\Application\FormTokens;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The form that adds a stock item, filled again with what was posted and why
 * it was refused.
 */
final readonly class StockForm
{
    /**
     * @param FormTokens $formTokens Issues the visitor's CSRF token.
     */
    public function __construct(
        private FormTokens $formTokens = new FormTokens(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     * @param StockEntry             $stockEntry    The values to show.
     * @param string                 $error         Why the last post was refused; empty for none.
     *
     * @return Html The form.
     */
    public function render(ServerRequestInterface $serverRequest, StockEntry $stockEntry, string $error): Html
    {
        $html = ($error === '' ? new Html('') : (new Alert(AlertSeverity::Error, $error, 'stock-error'))->render())
            ->append((new FormField('Name', 'name', new Input('name', 'name', $stockEntry->name)))->render())
            ->append((new FormField('Price, in dollars', 'price', new Input('price', 'price', $stockEntry->price)))->render())
            ->append((new FormField('In stock since (YYYY-MM-DD)', 'added_on', new Input('added_on', 'added_on', $stockEntry->addedOn)))
                ->render())
            ->append((new FormField('Certified', 'certified', new Checkbox('certified', 'certified', $stockEntry->certified)))->render())
            ->append((new Button('Add', 'submit', 'add-stock'))->render());

        return (new Form('stock-form', '/stock', $html, $this->formTokens->issue($serverRequest)))->render();
    }
}
