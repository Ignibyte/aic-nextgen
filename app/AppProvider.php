<?php

declare(strict_types=1);

namespace App;

use AIC\Container\Contracts\ServiceProvider;
use AIC\Container\Contracts\ServiceRegistry;
use App\Greeting\EnglishGreeter;
use App\Greeting\Greeter;
use App\Greeting\SpanishGreeter;
use Override;

/**
 * The app's services and plugins: its two Greeter plugins.
 */
final readonly class AppProvider implements ServiceProvider
{
    /**
     * @param ServiceRegistry $serviceRegistry Where the services and plugins go.
     */
    #[Override]
    public function register(ServiceRegistry $serviceRegistry): void
    {
        $serviceRegistry->singleton(EnglishGreeter::class, static fn (): EnglishGreeter => new EnglishGreeter());
        $serviceRegistry->tag(Greeter::class, EnglishGreeter::class);
        $serviceRegistry->singleton(SpanishGreeter::class, static fn (): SpanishGreeter => new SpanishGreeter());
        $serviceRegistry->tag(Greeter::class, SpanishGreeter::class);
    }
}
