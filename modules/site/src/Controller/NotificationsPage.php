<?php

declare(strict_types=1);

namespace App\Site\Controller;

use AIC\Component\Domain\Button;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\FormField;
use AIC\Component\Domain\Heading;
use AIC\Component\Domain\Input;
use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use AIC\Form\Application\FormTokens;
use AIC\Notification\Application\DatabaseChannel;
use App\Theme\Components\LineList\LineList;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /notifications: the demo user's latest notifications from the database
 * channel, and a button that sends one to mail and the database (#48).
 * `bin/aic queue:work` delivers them.
 */
final readonly class NotificationsPage implements Page
{
    /**
     * The demo's recipient, by user id and by mail.
     */
    public const string USER = 'demo';

    /**
     * @param PageDocuments   $pageDocuments   Makes the page.
     * @param DatabaseChannel $databaseChannel Reads the notifications.
     * @param FormTokens      $formTokens      Issues the visitor's CSRF token.
     */
    public function __construct(
        private PageDocuments $pageDocuments,
        private DatabaseChannel $databaseChannel,
        private FormTokens $formTokens = new FormTokens(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     *
     * @throws QueryException When the notifications cannot be read.
     * @throws LogicException When the required application state is unavailable.
     *
     * @return PageResponse The page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $latest = $this->databaseChannel->latest(self::USER, 5);
        $fields = [
            new FormField('Message', 'message', new Input('message', 'message', 'Hello from the demo.')),
            new Button('Send a notification', 'submit', 'notify'),
        ];

        return $this->pageDocuments->content(
            [
                new Heading('Notifications'),
                new LineList('notifications', $latest, 'None yet.'),
                new Form('', '/notifications', $fields, $this->formTokens->issue($serverRequest)->value()),
            ],
            200,
            'Notifications',
        );
    }
}
