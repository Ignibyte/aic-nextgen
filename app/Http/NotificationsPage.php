<?php

declare(strict_types=1);

namespace App\Http;

use AIC\Component\Domain\Button;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\FormField;
use AIC\Component\Domain\Input;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use AIC\Form\Application\FormTokens;
use AIC\Notification\Application\DatabaseChannel;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function array_map;
use function htmlspecialchars;
use function implode;

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
     * @param HtmlLayout      $htmlLayout      Makes the page.
     * @param DatabaseChannel $databaseChannel Reads the notifications.
     * @param FormTokens      $formTokens      Issues the visitor's CSRF token.
     */
    public function __construct(
        private HtmlLayout $htmlLayout,
        private DatabaseChannel $databaseChannel,
        private FormTokens $formTokens = new FormTokens(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     *
     * @throws QueryException When the notifications cannot be read.
     *
     * @return PageResponse The page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $latest = $this->databaseChannel->latest(self::USER, 5);
        $list = $latest === []
            ? '<p id="notifications">None yet.</p>'
            : '<ul id="notifications">' . implode('', array_map(
                static fn (string $line): string => '<li>' . htmlspecialchars($line) . '</li>',
                $latest,
            )) . '</ul>';
        $html = (new FormField('Message', 'message', new Input('message', 'message', 'Hello from the demo.')))->render()
            ->append((new Button('Send a notification', 'submit', 'notify'))->render());

        return $this->htmlLayout->content(
            '<h2>Notifications</h2>' . $list
            . (new Form('', '/notifications', $html, $this->formTokens->issue($serverRequest)))->render()->markup(),
            200,
        );
    }
}
