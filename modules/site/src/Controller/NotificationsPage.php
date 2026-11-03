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
use App\Site\Service\InboxLines;
use App\Theme\Components\LineList\LineList;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;
use function is_string;

/**
 * GET /notifications: the demo user's inbox (#202), newest first or after the
 * `after` id, a button that marks every one read, and a button that sends one
 * to mail and the database (#48). `bin/aic queue:work` delivers them.
 */
final readonly class NotificationsPage implements Page
{
    /**
     * The demo's recipient, by user id and by mail.
     */
    public const string USER = 'demo';

    /**
     * @param PageDocuments $pageDocuments Makes the page.
     * @param InboxLines    $inboxLines    Reads the inbox.
     * @param FormTokens    $formTokens    Issues the visitor's CSRF token.
     */
    public function __construct(
        private PageDocuments $pageDocuments,
        private InboxLines $inboxLines,
        private FormTokens $formTokens = new FormTokens(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session and an optional `after` id.
     *
     * @throws QueryException When the notifications cannot be read.
     * @throws LogicException When the required application state is unavailable.
     *
     * @return PageResponse The page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $query = $serverRequest->getQueryParams();
        $after = array_key_exists('after', $query) && is_string($query['after'])
            ? $query['after']
            : '';
        $lines = $this->inboxLines->lines(self::USER, $after);
        $guardFields = $this->formTokens->guard($serverRequest);
        $fields = [
            new FormField('Message', 'message', new Input('message', 'message', 'Hello from the demo.')),
            new Button('Send a notification', 'submit', 'notify'),
        ];

        return $this->pageDocuments->content(
            [
                new Heading('Notifications'),
                new LineList('notifications', $lines, 'None yet.'),
                new Form('', '/notifications/read', [new Button('Mark all read', 'submit', 'read')], $guardFields),
                new Form('', '/notifications', $fields, $guardFields),
            ],
            200,
            'Notifications',
        );
    }
}
