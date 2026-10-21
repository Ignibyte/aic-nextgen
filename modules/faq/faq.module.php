<?php

declare(strict_types=1);

/*
 * The faq module's manifest (#92): what it brings to the app. bin/aic-build
 * reads it and writes the wiring under app/Generated/; nothing reads it at run
 * time. Its classes live under src/ as App\Faq\.
 */

use AIC\Block\Contracts\Block;
use AIC\Module\Contracts\Binding;
use AIC\Module\Contracts\Module;
use AIC\Module\Contracts\ModuleRoute;
use AIC\Module\Contracts\PluginTag;
use AIC\Module\Contracts\RouteMethod;
use AIC\Schema\Contracts\Migration;
use App\Faq\Block\FaqNoteBlock;
use App\Faq\Component\FaqItem;
use App\Faq\Controller\FaqApi;
use App\Faq\Controller\FaqPage;
use App\Faq\Service\DemoFaqEntries;
use App\Faq\Service\FaqEntries;

return new class () implements Module {
    /**
     * @return string The folder's name.
     */
    #[Override]
    public function name(): string
    {
        return 'faq';
    }

    /**
     * @return list<string> None: it uses only the app and the framework.
     */
    #[Override]
    public function requires(): array
    {
        return [];
    }

    /**
     * @return list<ModuleRoute> The page and its JSON.
     */
    #[Override]
    public function routes(): array
    {
        return [
            new ModuleRoute(RouteMethod::Get, '/faq', FaqPage::class),
            new ModuleRoute(RouteMethod::Get, '/api/faq', FaqApi::class),
        ];
    }

    /**
     * @return list<Binding> The questions, from code.
     */
    #[Override]
    public function services(): array
    {
        return [new Binding(FaqEntries::class, DemoFaqEntries::class)];
    }

    /**
     * @return list<class-string<Block>> The note under the FAQ.
     */
    #[Override]
    public function blocks(): array
    {
        return [FaqNoteBlock::class];
    }

    /**
     * @return list<PluginTag> None.
     */
    #[Override]
    public function plugins(): array
    {
        return [];
    }

    /**
     * @return list<class-string<Migration>> None: the questions live in code.
     */
    #[Override]
    public function migrations(): array
    {
        return [];
    }

    /**
     * @return list<class-string> The question component.
     */
    #[Override]
    public function exports(): array
    {
        return [FaqItem::class];
    }
};
