<?php

declare(strict_types=1);

namespace App\Greeting\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Module\Attribute\Tagged;
use App\Greeting\Component\Greetings;
use App\Greeting\Service\Greeter;
use App\Site\Service\HtmlLayout;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function is_string;

/**
 * The greetings at `/hello/{name}`: one per Greeter plugin, in tag order. The
 * route's parameter reaches the controller as a request attribute.
 */
final readonly class GreetingPage implements Page
{
    /**
     * @param HtmlLayout   $htmlLayout The document around the page.
     * @param list<object> $greeters   The Greeter plugins, in tag order (`#[Tagged]`).
     */
    public function __construct(
        private HtmlLayout $htmlLayout,
        #[Tagged(Greeter::class)]
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
        $greetings = [];

        foreach ($this->greeters as $greeter) {
            if (!($greeter instanceof Greeter)) {
                continue;
            }

            $greetings[] = $greeter->greet($name);
        }

        return $this->htmlLayout->content([new Greetings($greetings)], 200);
    }
}
