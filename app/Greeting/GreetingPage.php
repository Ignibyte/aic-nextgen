<?php

declare(strict_types=1);

namespace App\Greeting;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Site\HtmlLayout;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function htmlspecialchars;
use function is_string;

/**
 * The greetings at `/hello/{name}`: one per Greeter plugin, in tag order. The
 * route's parameter reaches the controller as a request attribute.
 */
final readonly class GreetingPage implements Page
{
    /**
     * @param HtmlLayout   $htmlLayout The document around the page.
     * @param list<object> $greeters   The Greeter plugins, as the container's `tagged()` gives them.
     */
    public function __construct(
        private HtmlLayout $htmlLayout,
        private array $greeters,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with the `name` attribute.
     *
     * @return PageResponse The greeting.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $attribute = $serverRequest->getAttribute('name');
        $name = is_string($attribute)
            ? $attribute
            : '';
        $items = '';

        foreach ($this->greeters as $greeter) {
            if (!($greeter instanceof Greeter)) {
                continue;
            }

            $items .= '<li>' . htmlspecialchars($greeter->greet($name)) . '</li>';
        }

        return $this->htmlLayout->content('<ul id="greetings">' . $items . '</ul>', 200);
    }
}
