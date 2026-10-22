<?php

declare(strict_types=1);

/*
 * The greeting module's manifest (#93): the greeting pages, the Greeter
 * plugins, greet on the console, and the greeting form the home page shows.
 * bin/aic-build reads it; nothing reads it at run time.
 */

use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Greeting\Command\Greet;
use App\Greeting\Controller\GreetingPage;
use App\Greeting\Controller\GreetingPost;
use App\Greeting\Form\GreetingForm;
use App\Greeting\Service\EnglishGreeter;
use App\Greeting\Service\Greeter;
use App\Greeting\Service\SpanishGreeter;

return (new ModuleManifest('greeting'))
    ->requires('site')
    ->route(RouteMethod::Get, '/hello/{name}', GreetingPage::class)
    ->route(RouteMethod::Post, '/hello', GreetingPost::class)
    ->plugin(Greeter::class, EnglishGreeter::class)
    ->plugin(Greeter::class, SpanishGreeter::class)
    ->command('greet', Greet::class)
    ->export(GreetingForm::class);
