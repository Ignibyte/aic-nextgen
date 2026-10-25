<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Region;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;

use function class_exists;
use function is_a;
use function preg_match;

/**
 * Reads a theme's page region data (#153): a final readonly Component whose constructor promotes one public
 * nonnullable Region per region. The regions are the constructor's parameters, named after their properties.
 */
final readonly class ThemePageData
{
    /**
     * @param string $class The theme's page data class, `App\Theme\Layouts\Page`.
     *
     * @throws InvalidArgumentException When it is absent or not a Component.
     *
     * @return class-string<Component> The checked class.
     */
    public function page(string $class): string
    {
        if (!class_exists($class) || !is_a($class, Component::class, true)) {
            throw new InvalidArgumentException($class . ': the theme needs layouts/Page.php, a Component holding its regions.');
        }

        return $class;
    }

    /**
     * @param class-string<Component> $class The theme's page data class.
     *
     * @throws InvalidArgumentException When it is not final readonly page data, or a region is not a promoted Region.
     *
     * @return non-empty-list<string> Its region names, in constructor order.
     */
    public function regions(string $class): array
    {
        $reflectionClass = new ReflectionClass($class);
        $constructor = $reflectionClass->getConstructor();

        if ($constructor === null || !$reflectionClass->isFinal() || !$reflectionClass->isReadOnly()) {
            throw new InvalidArgumentException($class . ': page data is a final readonly class whose constructor takes its regions.');
        }

        $parameters = $constructor->getParameters();

        if ($parameters === []) {
            throw new InvalidArgumentException($class . ': page data is a final readonly class whose constructor takes its regions.');
        }

        $regions = [];

        foreach ($parameters as $parameter) {
            $regions[] = $this->region($reflectionClass, $parameter);
        }

        return $regions;
    }

    /**
     * @param ReflectionClass<object> $reflectionClass     The page data class.
     * @param ReflectionParameter     $reflectionParameter One constructor parameter.
     *
     * @throws InvalidArgumentException When it is not a public promoted nonnullable Region with a region name.
     *
     * @return string Its region name.
     */
    private function region(ReflectionClass $reflectionClass, ReflectionParameter $reflectionParameter): string
    {
        $name = $reflectionParameter->getName();
        $type = $reflectionParameter->getType();

        if (preg_match('/^[a-z][a-z0-9]*\\z/', $name) !== 1) {
            throw new InvalidArgumentException($name . ': a region property is named in lowercase letters and digits.');
        }

        if (
            !$reflectionParameter->isPromoted()
            || !$type instanceof ReflectionNamedType
            || $type->allowsNull()
            || $type->getName() !== Region::class
        ) {
            throw new InvalidArgumentException($name . ': page inputs are promoted nonnullable Region properties.');
        }

        $this->publicRegion($reflectionClass, $name);

        return $name;
    }

    /**
     * @param ReflectionClass<object> $reflectionClass The page data class.
     * @param string                  $name            One checked promoted Region input.
     *
     * @throws InvalidArgumentException When its property is absent or not public.
     */
    private function publicRegion(ReflectionClass $reflectionClass, string $name): void
    {
        try {
            $property = $reflectionClass->getProperty($name);
        } catch (ReflectionException $reflectionException) {
            throw new InvalidArgumentException($name . ': a region property is missing.', 0, $reflectionException);
        }

        if (!$property->isPublic()) {
            throw new InvalidArgumentException($name . ': a page region property must be public.');
        }
    }
}
