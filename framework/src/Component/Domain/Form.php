<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Html;
use AIC\Form\Contracts\FormToken;
use Override;

/**
 * A form around its fields and buttons.
 */
final readonly class Form implements Component
{
    use RendersMarkup;

    /**
     * @param string    $id        The element id; empty for none.
     * @param string    $action    Where it posts.
     * @param Html      $html      The fields, alerts and buttons, in order.
     * @param FormToken $formToken The visitor's CSRF token, rendered first with the honeypot (#42).
     */
    public function __construct(
        private string $id,
        private string $action,
        private Html $html,
        private FormToken $formToken,
    ) {
    }

    /**
     * @return Html `<form class="aic-form" method="post">`.
     */
    #[Override]
    public function render(): Html
    {
        $open = '<form' . self::attribute('id', $this->id) . ' method="post"'
            . self::attribute('action', $this->action) . ' class="aic-form">';

        return (new Html($open))
            ->append($this->formToken->fields())
            ->append($this->html)
            ->append(new Html('</form>'));
    }
}
