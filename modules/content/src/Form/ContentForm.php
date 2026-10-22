<?php

declare(strict_types=1);

namespace App\Content\Form;

use AIC\Component\Domain\Alert;
use AIC\Component\Domain\AlertSeverity;
use AIC\Component\Domain\Button;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\FormField;
use AIC\Component\Domain\Input;
use AIC\Component\Domain\Textarea;
use AIC\Form\Application\FormTokens;
use App\Content\Entity\ContentPage;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The forms that write content pages (#50): the editor, and the one that
 * starts a page by its slug.
 */
final readonly class ContentForm
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
     * @param ContentPage            $contentPage   The page as it stands, or as posted.
     * @param string                 $error         Why the last save failed; empty for none.
     *
     * @return Form The editor, posting to the page's path under /account.
     */
    public function editor(ServerRequestInterface $serverRequest, ContentPage $contentPage, string $error): Form
    {
        $alerts = $error === ''
            ? []
            : [new Alert(AlertSeverity::Error, $error, 'content-error')];
        $fields = [
            ...$alerts,
            new FormField('Title', 'title', new Input('title', 'title', $contentPage->title)),
            new FormField('Text', 'body', new Textarea('body', 'body', $contentPage->body)),
            new Button('Save', 'submit', ''),
        ];

        return new Form('content-form', '/account/pages/' . $contentPage->slug, $fields, $this->formTokens->issue($serverRequest));
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     * @param string                 $slug          The slug to show again.
     * @param string                 $error         Why it was refused; empty for none.
     *
     * @return Form The form that starts a page by its slug.
     */
    public function starter(ServerRequestInterface $serverRequest, string $slug, string $error): Form
    {
        $alerts = $error === ''
            ? []
            : [new Alert(AlertSeverity::Error, $error, 'slug-error')];
        $fields = [
            ...$alerts,
            new FormField('New page, by slug', 'slug', new Input('slug', 'slug', $slug)),
            new Button('Write it', 'submit', 'start-page'),
        ];

        return new Form('content-start', '/account/pages', $fields, $this->formTokens->issue($serverRequest));
    }
}
