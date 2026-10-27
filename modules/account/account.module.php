<?php

declare(strict_types=1);

/*
 * The account module's manifest (#126, from #93's auth and session): sign-in
 * and sign-out, the account page, the sign-in middleware guarding /account,
 * the users table and user:add, the visit counter, and the uploads page with
 * its rules as a factory. The build reads it; nothing reads it at run
 * time.
 */

use AIC\Auth\Application\PgcryptoPasswordHasher;
use AIC\Auth\Application\RequireAuthenticationMiddleware;
use AIC\Auth\Contracts\PasswordHasher;
use AIC\Auth\Contracts\UserProvider;
use AIC\Module\Contracts\MiddlewarePosition;
use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Account\Command\UserAdd;
use App\Account\Controller\AccountPage;
use App\Account\Controller\LoginPage;
use App\Account\Controller\LoginPost;
use App\Account\Controller\LogoutPost;
use App\Account\Controller\UploadsPage;
use App\Account\Controller\UploadsPost;
use App\Account\Controller\VisitsForget;
use App\Account\Controller\VisitsPage;
use App\Account\Migration\AddUserIds;
use App\Account\Migration\CreateUsersTable;
use App\Account\Repository\UserRepository;
use App\Account\Service\AccessRules;
use App\Account\Service\UploadRules;

return (new ModuleManifest('account'))
    ->requires('site')
    ->route(RouteMethod::Get, '/login', LoginPage::class)
    ->route(RouteMethod::Post, '/login', LoginPost::class)
    ->route(RouteMethod::Post, '/logout', LogoutPost::class)
    ->route(RouteMethod::Get, '/account', AccountPage::class)
    ->route(RouteMethod::Get, '/visits', VisitsPage::class)
    ->route(RouteMethod::Post, '/visits/forget', VisitsForget::class)
    ->route(RouteMethod::Get, '/uploads', UploadsPage::class)
    ->route(RouteMethod::Post, '/uploads', UploadsPost::class)
    ->binding(UserProvider::class, UserRepository::class)
    ->binding(PasswordHasher::class, PgcryptoPasswordHasher::class)
    ->middleware(RequireAuthenticationMiddleware::class, MiddlewarePosition::AfterSession)
    ->protectedPrefix('/account')
    ->factory(AccessRules::class)
    ->factory(UploadRules::class)
    ->command('user:add', UserAdd::class)
    ->migration(CreateUsersTable::class)
    ->migration(AddUserIds::class);
