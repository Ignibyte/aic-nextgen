<?php

declare(strict_types=1);

namespace App\Account\Form;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\Alert;
use AIC\Component\Domain\AlertSeverity;
use AIC\Component\Domain\Button;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\Heading;
use AIC\Component\Domain\HiddenInput;
use AIC\Component\Domain\Paragraph;
use AIC\Form\Application\FormTokens;
use AIC\OAuth\Domain\AuthorizationRequest;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

use function implode;

/**
 * The consent page's parts (#331): who asks, the scopes it would hold, and
 * two forms that send the request back to `/oauth/authorize` with the
 * person's decision, allow or deny.
 */
final readonly class ConsentForm
{
    /**
     * Where the decision is posted.
     */
    public const string ACTION = '/oauth/authorize';

    /**
     * @param FormTokens $formTokens Issues the visitor's CSRF token.
     */
    public function __construct(
        private FormTokens $formTokens = new FormTokens(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest        The request, with its session.
     * @param AuthorizationRequest   $authorizationRequest The sound request.
     * @param list<string>           $grantable            The scopes asked that the person can grant.
     *
     * @throws LogicException When the request carries no session.
     *
     * @return list<Component> The heading, what it asks, and the two forms.
     */
    public function consent(
        ServerRequestInterface $serverRequest,
        AuthorizationRequest $authorizationRequest,
        array $grantable,
    ): array {
        $asks = $grantable === []
            ? 'It asks for ' . implode(', ', $authorizationRequest->scopes) . ', and you hold none of them.'
            : 'It would act for you with ' . implode(', ', $grantable) . '.';

        return [
            new Heading('Allow ' . $authorizationRequest->clientId . '?'),
            new Paragraph($asks, 'consent-scopes'),
            $this->decision($serverRequest, $authorizationRequest, 'allow', 'Allow'),
            $this->decision($serverRequest, $authorizationRequest, 'deny', 'Deny'),
        ];
    }

    /**
     * @param string $reason Why the request was refused.
     *
     * @return list<Component> The heading and the reason.
     */
    public function refused(string $reason): array
    {
        return [new Heading('Authorization refused'), new Alert(AlertSeverity::Error, $reason, 'consent-refused')];
    }

    /**
     * @param ServerRequestInterface $serverRequest        The request, with its session.
     * @param AuthorizationRequest   $authorizationRequest The request.
     * @param string                 $decision             `allow` or `deny`.
     * @param string                 $label                The button's label.
     *
     * @throws LogicException When the request carries no session.
     *
     * @return Form A form sending the request back with the decision.
     */
    private function decision(
        ServerRequestInterface $serverRequest,
        AuthorizationRequest $authorizationRequest,
        string $decision,
        string $label,
    ): Form {
        $fields = [];

        foreach ($authorizationRequest->fields() as $name => $value) {
            $fields[] = new HiddenInput($name, $value);
        }

        $fields[] = new HiddenInput('decision', $decision);
        $fields[] = new Button($label, 'submit', 'consent-' . $decision);

        return new Form('consent-' . $decision . '-form', self::ACTION, $fields, $this->formTokens->guard($serverRequest));
    }
}
