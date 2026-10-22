<?php

declare(strict_types=1);

namespace App\Site\Controller;

use AIC\Config\Domain\AppConfig;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Site\Component\Home;
use App\Site\Entity\HomeDemo;
use App\Site\Entity\HomeFact;
use App\Site\Form\GreetingForm;
use App\Site\Service\HtmlLayout;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function array_map;
use function explode;
use function in_array;
use function urldecode;

/**
 * The home page at `/` (#49): a link to every demo, then what the app and
 * the request carried, and the greeting form.
 */
final readonly class HomePage implements Page
{
    /**
     * Every demo, by path: each shows one system at work.
     */
    private const array DEMOS = [
        '/account' => 'Account (sign-in, protected route)',
        '/api/status' => 'Status API (JSON)',
        '/download/hello.txt' => 'A download (byte ranges)',
        '/events?count=5' => 'Server-Sent Events',
        '/health' => 'Health check',
        '/hello/World' => 'Greeting plugins',
        '/jobs' => 'Jobs, the queue and the scheduler',
        '/notes' => 'Notes (entity CRUD, templates, signed forms)',
        '/notifications' => 'Notifications (mail and database)',
        '/stock' => 'Stock (date, numeric and boolean columns)',
        '/ticks' => 'Live ticks (a Tessera stream)',
        '/uploads' => 'Uploads (checked by content)',
        '/visits' => 'Visits (sessions)',
    ];

    /**
     * The demos that answer with no HTML page: Tessera leaves their links to the browser (#71).
     */
    private const array PLAIN = ['/api/status', '/download/hello.txt', '/events?count=5', '/health'];

    /**
     * @param HtmlLayout   $htmlLayout   The document around the page.
     * @param GreetingForm $greetingForm The form posting a name to /hello.
     * @param AppConfig    $appConfig    The app's settings.
     * @param string       $bootedAt     When the app booted to serve this request, ISO 8601 in UTC.
     */
    public function __construct(
        private HtmlLayout $htmlLayout,
        private GreetingForm $greetingForm,
        private AppConfig $appConfig,
        private string $bootedAt,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @return PageResponse The home page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $demos = [];

        foreach (self::DEMOS as $path => $label) {
            $demos[] = new HomeDemo($path, $label, in_array($path, self::PLAIN, true));
        }

        $facts = [
            new HomeFact('app-name', 'App name (config/app.php)', $this->appConfig->name),
            new HomeFact('app-booted', 'App booted', $this->bootedAt),
            new HomeFact('request-method', 'Request method', $serverRequest->getMethod()),
            new HomeFact('request-path', 'Request path', $serverRequest->getUri()->getPath()),
        ];
        $query = $serverRequest->getUri()->getQuery();
        $pairs = array_map(urldecode(...), $query === '' ? [] : explode('&', $query));

        return $this->htmlLayout->content([new Home($demos, $facts, $pairs, $this->greetingForm->form($serverRequest, '', ''))], 200);
    }
}
