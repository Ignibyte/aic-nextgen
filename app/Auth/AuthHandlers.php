<?php

declare(strict_types=1);

namespace App\Auth;

use AIC\Auth\Application\Authenticator;
use AIC\Auth\Application\PgcryptoPasswordHasher;
use AIC\Controller\Application\PageHandlers;
use AIC\Database\Contracts\Database;
use App\Site\HtmlLayout;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The sign-in pages and the account page (#9), each wrapped in the Page
 * handler, by the handler id config/routes.php names.
 */
final readonly class AuthHandlers
{
    /**
     * @param HtmlLayout   $htmlLayout   Makes the pages.
     * @param PageHandlers $pageHandlers Wraps each page with the response writer and the blocks.
     * @param Database     $database     Holds the users.
     */
    public function __construct(
        private HtmlLayout $htmlLayout,
        private PageHandlers $pageHandlers,
        private Database $database,
    ) {
    }

    /**
     * @return array<string, RequestHandlerInterface> The handlers by id.
     */
    public function byId(): array
    {
        $authenticator = new Authenticator(new UserRepository($this->database), new PgcryptoPasswordHasher($this->database));
        $loginForm = new LoginForm();

        return [
            AccountPage::class => $this->pageHandlers->handler(new AccountPage($this->htmlLayout, $authenticator)),
            LoginPage::class => $this->pageHandlers->handler(new LoginPage($this->htmlLayout, $loginForm)),
            LoginPost::class => $this->pageHandlers->handler(new LoginPost($this->htmlLayout, $loginForm, $authenticator)),
            LogoutPost::class => $this->pageHandlers->handler(new LogoutPost($authenticator)),
        ];
    }
}
