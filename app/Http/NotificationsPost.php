<?php

declare(strict_types=1);

namespace App\Http;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use AIC\Database\Contracts\QueryException;
use AIC\Form\Application\PostedFields;
use AIC\Notification\Application\Notifier;
use AIC\Notification\Contracts\Notification;
use JsonException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /notifications: sends the demo user the posted message by mail and to
 * the database (#48), each delivery a queued job, and goes back.
 */
final readonly class NotificationsPost implements Page
{
    /**
     * @param Notifier     $notifier     Queues the deliveries.
     * @param PostedFields $postedFields Reads the message.
     */
    public function __construct(
        private Notifier $notifier,
        private PostedFields $postedFields = new PostedFields(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form, with its `message`.
     *
     * @throws JsonException  When a payload cannot be encoded.
     * @throws QueryException When a job cannot be queued.
     *
     * @return PageResponse 303 to /notifications.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $message = $this->postedFields->value($serverRequest, 'message');
        $body = $message === ''
            ? 'This went out by mail and to the database.'
            : $message;
        $notification = new Notification(NotificationsPage::USER, 'demo@aic-nextgen.test', 'Hello from AIC', $body);
        $this->notifier->send($notification, ['mail', 'database']);

        return new PageRedirect('/notifications');
    }
}
