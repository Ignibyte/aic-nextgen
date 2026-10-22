<?php

declare(strict_types=1);

namespace App\Note\Form;

use AIC\Component\Domain\Alert;
use AIC\Component\Domain\AlertSeverity;
use AIC\Component\Domain\Button;
use AIC\Component\Domain\Checkbox;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\FormField;
use AIC\Component\Domain\Input;
use AIC\Form\Application\FormTokens;
use LogicException;
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
     * @throws LogicException When the required application state is unavailable.
     *
     * @return Form The form.
     */
    public function form(ServerRequestInterface $serverRequest, StockEntry $stockEntry, string $error): Form
    {
        $alerts = $error === ''
            ? []
            : [new Alert(AlertSeverity::Error, $error, 'stock-error')];
        $fields = [
            ...$alerts,
            new FormField('Name', 'name', new Input('name', 'name', $stockEntry->name)),
            new FormField('Price, in dollars', 'price', new Input('price', 'price', $stockEntry->price)),
            new FormField('In stock since (YYYY-MM-DD)', 'added_on', new Input('added_on', 'added_on', $stockEntry->addedOn)),
            new FormField('Certified', 'certified', new Checkbox('certified', 'certified', $stockEntry->certified)),
            new Button('Add', 'submit', 'add-stock'),
        ];

        return new Form('stock-form', '/stock', $fields, $this->formTokens->issue($serverRequest));
    }
}
