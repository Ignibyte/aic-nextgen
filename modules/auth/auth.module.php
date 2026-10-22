<?php

declare(strict_types=1);

/*
 * The auth module's manifest (#93): sign-in and sign-out, the account page,
 * the sign-in middleware guarding /account, the users table, and user:add.
 * bin/aic-build reads it; nothing reads it at run time.
 */

use AIC\Auth\Application\PgcryptoPasswordHasher;
use AIC\Auth\Application\RequireAuthenticationMiddleware;
use AIC\Auth\Contracts\PasswordHasher;
use AIC\Auth\Contracts\UserProvider;
use AIC\Module\Contracts\MiddlewarePosition;
use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Auth\Command\UserAdd;
use App\Auth\Controller\AccountPage;
use App\Auth\Controller\LoginPage;
use App\Auth\Controller\LoginPost;
use App\Auth\Controller\LogoutPost;
use App\Auth\Migration\CreateUsersTable;
use App\Auth\Repository\UserRepository;
use App\Auth\Service\AccessRules;

return (new ModuleManifest('auth'))
    ->requires('site')
    ->route(RouteMethod::Get, '/login', LoginPage::class)
    ->route(RouteMethod::Post, '/login', LoginPost::class)
    ->route(RouteMethod::Post, '/logout', LogoutPost::class)
    ->route(RouteMethod::Get, '/account', AccountPage::class)
    ->binding(UserProvider::class, UserRepository::class)
    ->binding(PasswordHasher::class, PgcryptoPasswordHasher::class)
    ->middleware(RequireAuthenticationMiddleware::class, MiddlewarePosition::AfterSession)
    ->protectedPrefix('/account')
    ->factory(AccessRules::class)
    ->command('user:add', UserAdd::class)
    ->migration(CreateUsersTable::class);
