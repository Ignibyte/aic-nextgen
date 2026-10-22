<?php

declare(strict_types=1);

/*
 * The job module's manifest (#93): the jobs and notifications demos, the demo
 * job and its every-minute schedule, and the subscribers tables. The notify
 * job is the framework's. bin/aic-build reads it; nothing reads it at run time.
 */

use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Job\Controller\JobsPage;
use App\Job\Controller\JobsPost;
use App\Job\Controller\NotificationsPage;
use App\Job\Controller\NotificationsPost;
use App\Job\Job\DemoJob;
use App\Job\Migration\CreateSubscribersTable;
use App\Job\Migration\MakeSubscriberEmailUnique;

return (new ModuleManifest('job'))
    ->requires('site')
    ->route(RouteMethod::Get, '/jobs', JobsPage::class)
    ->route(RouteMethod::Post, '/jobs', JobsPost::class)
    ->route(RouteMethod::Get, '/notifications', NotificationsPage::class)
    ->route(RouteMethod::Post, '/notifications', NotificationsPost::class)
    ->job('demo', DemoJob::class)
    ->schedule('demo-every-minute', 'demo', ['mode' => 'succeed'], '* * * * *')
    ->migration(CreateSubscribersTable::class)
    ->migration(MakeSubscriberEmailUnique::class);
