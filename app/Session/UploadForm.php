<?php

declare(strict_types=1);

namespace App\Session;

use AIC\Component\Contracts\Html;
use AIC\Component\Domain\Alert;
use AIC\Component\Domain\AlertSeverity;
use AIC\Component\Domain\Button;
use AIC\Component\Domain\FileInput;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\FormEncoding;
use AIC\Component\Domain\FormField;
use AIC\Form\Application\FormTokens;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The form that uploads one file to /uploads (#44), with the reason when the
 * last one was refused.
 */
final readonly class UploadForm
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
     * @param string                 $error         Why the last upload was refused; empty for none.
     *
     * @return Html The form.
     */
    public function render(ServerRequestInterface $serverRequest, string $error): Html
    {
        $accept = 'image/png,image/jpeg,image/gif,application/pdf,text/plain';
        $html = ($error === '' ? new Html('') : (new Alert(AlertSeverity::Error, $error, 'upload-error'))->render())
            ->append((new FormField('File', 'file', new FileInput('file', 'file', $accept)))->render())
            ->append((new Button('Upload', 'submit', ''))->render());

        return (new Form('upload-form', '/uploads', $html, $this->formTokens->issue($serverRequest), FormEncoding::Multipart))->render();
    }
}
