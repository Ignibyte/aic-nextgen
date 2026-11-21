<?php

declare(strict_types=1);

namespace App\Scaffold\Service;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Region;
use AIC\Component\Contracts\Regions;
use AIC\Component\Domain\Alert;
use AIC\Component\Domain\AlertSeverity;
use AIC\Component\Domain\Button;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\FormField;
use AIC\Component\Domain\Input;
use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Domain\PageDocument;
use AIC\Form\Application\FormTokens;
use AIC\Form\Application\PostedFields;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The content region of the page at /scaffold, made by bin/aic make:page-action
 * (#322): the form that posts to /scaffold/scaffold-pin. Place `components()` in that page's
 * content region; Tessera submits a form to the region it sits in, so the
 * action's answer replaces it in place.
 */
final readonly class ScaffoldPinView
{
    /**
     * @param PageDocuments $pageDocuments Makes the answer.
     * @param FormTokens    $formTokens    The form's guard fields.
     * @param PostedFields  $postedFields  Reads the posted value back into the form.
     */
    public function __construct(
        private PageDocuments $pageDocuments,
        private FormTokens $formTokens,
        private PostedFields $postedFields = new PostedFields(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     * @param string                 $reason        Why the action refused; empty for none.
     *
     * @throws LogicException When the request carries no session.
     *
     * @return list<Component> The form, its field marked and the reason above it when there is one.
     */
    public function components(ServerRequestInterface $serverRequest, string $reason): array
    {
        $value = $reason === ''
            ? ''
            : $this->postedFields->value($serverRequest, 'value');
        $formField = new FormField('Value', 'scaffold-pin-value', new Input('scaffold-pin-value', 'value', $value, $reason !== ''));
        $button = new Button('Scaffold Pin', 'submit', 'scaffold-pin-go');
        $fields = $reason === ''
            ? [$formField, $button]
            : [new Alert(AlertSeverity::Error, $reason, 'scaffold-pin-reason'), $formField, $button];

        return [new Form('scaffold-pin', '/scaffold/scaffold-pin', $fields, $this->formTokens->guard($serverRequest))];
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form, with its session.
     * @param string                 $reason        Why the action refused; empty when it was done.
     *
     * @throws LogicException When the request carries no session, or the region is not the layout's.
     *
     * @return PageDocument The content region, 200, or 422 with the reason.
     */
    public function page(ServerRequestInterface $serverRequest, string $reason): PageDocument
    {
        return $this->pageDocuments->page(
            new Regions([new Region('content', $this->components($serverRequest, $reason))]),
            $reason === '' ? 200 : 422,
            'Scaffold Pin',
        );
    }
}
