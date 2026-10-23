<?php

declare(strict_types=1);

namespace Gates\PhpStan;

use InvalidArgumentException;
use PHPStan\Reflection\ReflectionProvider;

/**
 * Constructs the registered PHPStan rules with their real container dependencies.
 */
final readonly class RuleFactory
{
    /**
     * @param string             $class              The rule class selected by the consumed configuration.
     * @param ReflectionProvider $reflectionProvider PHPStan's reflection service.
     *
     * @throws InvalidArgumentException When a registration names an unsupported rule.
     *
     * @return HtmlMarkupCallableRule|HtmlMarkupReadRule|RenderTreeInputRule|TaggedServiceTypeRule The registered rule.
     */
    public function __invoke(
        string $class,
        ReflectionProvider $reflectionProvider,
    ): HtmlMarkupCallableRule|HtmlMarkupReadRule|RenderTreeInputRule|TaggedServiceTypeRule {
        return match ($class) {
            HtmlMarkupCallableRule::class => new HtmlMarkupCallableRule(),
            HtmlMarkupReadRule::class => new HtmlMarkupReadRule(),
            RenderTreeInputRule::class => new RenderTreeInputRule($reflectionProvider),
            TaggedServiceTypeRule::class => new TaggedServiceTypeRule($reflectionProvider),
            default => throw new InvalidArgumentException('Unsupported PHPStan rule registration: ' . $class),
        };
    }
}
