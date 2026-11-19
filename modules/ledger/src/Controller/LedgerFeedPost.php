<?php

declare(strict_types=1);

namespace App\Ledger\Controller;

use AIC\Broadcast\Application\Broadcaster;
use AIC\Broadcast\Domain\Channel;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use AIC\Database\Contracts\QueryException;
use AIC\Form\Application\PostedFields;
use App\Ledger\Service\FeedEvents;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function mb_strlen;
use function trim;

/**
 * POST /ledger/feed (#314): publishes the posted line, 1 to 200 characters,
 * to `ledger.feed`, where every open feed page shows it, then goes back to
 * the feed. A blank or longer line publishes nothing.
 */
final readonly class LedgerFeedPost implements Page
{
    /**
     * @param Broadcaster  $broadcaster  Publishes the post.
     * @param PostedFields $postedFields Reads the posted line.
     */
    public function __construct(
        private Broadcaster $broadcaster,
        private PostedFields $postedFields = new PostedFields(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The post.
     *
     * @throws InvalidArgumentException When the channel is not one.
     * @throws QueryException           When the publish fails.
     *
     * @return PageResponse 303 to the feed.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $text = trim($this->postedFields->value($serverRequest, 'text'));

        if ($text !== '' && mb_strlen($text) <= 200) {
            $this->broadcaster->publish(new Channel(FeedEvents::CHANNEL), $text);
        }

        return new PageRedirect('/ledger/feed');
    }
}
