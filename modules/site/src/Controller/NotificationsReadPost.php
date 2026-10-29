<?php

declare(strict_types=1);

namespace App\Site\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use AIC\Database\Contracts\QueryException;
use AIC\Form\Application\PostedFields;
use AIC\Notification\Application\Inbox;
use AIC\Routing\Domain\RouteNotFoundException;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function ctype_digit;
use function strlen;

/**
 * POST /notifications/read (#202): marks the demo user's notification `id`
 * read, or all of them without one, and goes back to the inbox. An id that is
 * not the user's is 404.
 */
final readonly class NotificationsReadPost implements Page
{
    /**
     * @param Inbox        $inbox        The user's kept notifications.
     * @param PostedFields $postedFields Reads the posted id.
     */
    public function __construct(
        private Inbox $inbox,
        private PostedFields $postedFields = new PostedFields(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The post, with an optional `id`.
     *
     * @throws QueryException           When the notifications cannot be written.
     * @throws RouteNotFoundException   When the id is not one of the user's notifications.
     * @throws InvalidArgumentException When the redirect is invalid.
     *
     * @return PageResponse Back to /notifications.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $id = $this->postedFields->value($serverRequest, 'id');

        if ($id === '') {
            $this->inbox->markAllRead(NotificationsPage::USER);

            return new PageRedirect('/notifications');
        }

        if (!ctype_digit($id) || strlen($id) > 9 || !$this->inbox->markRead(NotificationsPage::USER, (int) $id)) {
            throw new RouteNotFoundException('/notifications/read');
        }

        return new PageRedirect('/notifications');
    }
}
