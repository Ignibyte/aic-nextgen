<?php

declare(strict_types=1);

namespace Gates\PhpStan;

use AIC\Component\Application\RenderPass;
use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Frame;
use Gates\Domain\RegexCaptures;
use Override;
use ReflectionClassConstant;
use ReflectionEnumUnitCase;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use ShipMonk\PHPStan\DeadCode\Provider\ReflectionBasedMemberUsageProvider;
use ShipMonk\PHPStan\DeadCode\Provider\VirtualUsageData;

use function array_fill_keys;
use function array_key_exists;
use function file_get_contents;
use function getcwd;
use function glob;
use function is_a;
use function ltrim;
use function str_starts_with;

/**
 * Templates run as templates (#155), so PHPStan never reads them, and its dead-code detector would call a component's
 * props and the render pass's template methods unused. This provider reads the escaped templates the lookups require
 * and the framework's runtime templates: a component's or frame's property is read when a template reads `->name`, a
 * `RenderPass` method is used when a template calls it, and a constant or enum case when a template names it fully
 * qualified. A prop no template names is still reported.
 *
 * @phpstan-type TemplateReads array{properties: array<string, true>, methods: array<string, true>, constants: array<string, true>}
 *
 * @psalm-type TemplateReads = array{properties: array<string, true>, methods: array<string, true>, constants: array<string, true>}
 */
final class TemplateUsageProvider extends ReflectionBasedMemberUsageProvider
{
    use RegexCaptures;

    /**
     * The folders, relative to the project, whose `.phtml` files are read: the app's escaped templates, the atoms'
     * (in the source package or an installed one), and the framework's runtime templates.
     */
    private const array FOLDERS = [
        'generated/templates',
        'framework/src/Component/Application/Templates',
        'framework/templates/tessera',
        'vendor/aic/framework/src/Component/Application/Templates',
        'vendor/aic/framework/templates/tessera',
    ];

    /**
     * How deep under a read folder a template may sit.
     */
    private const array DEPTHS = ['/*.phtml', '/*/*.phtml', '/*/*/*.phtml', '/*/*/*/*.phtml', '/*/*/*/*/*.phtml'];

    /**
     * What the templates read, once read: `properties`, `methods` and `constants` (`Class::NAME`), each a set.
     *
     * @var TemplateReads|null
     */
    private ?array $reads = null;

    /**
     * @param ReflectionProperty $property A property.
     *
     * @throws RuntimeException When a template cannot be read.
     *
     * @return VirtualUsageData|null Used when it is a prop of a component, a frame or a theme value class that a template
     *                               reads.
     */
    #[Override]
    protected function shouldMarkPropertyAsRead(ReflectionProperty $property): ?VirtualUsageData
    {
        $class = $property->getDeclaringClass()->getName();
        $data = is_a($class, Component::class, true) || is_a($class, Frame::class, true) || str_starts_with($class, 'App\\Theme\\');

        return $data && array_key_exists($property->getName(), $this->reads()['properties'])
            ? VirtualUsageData::withNote('read by a template (#155)')
            : null;
    }

    /**
     * @param ReflectionMethod $method A method.
     *
     * @throws RuntimeException When a template cannot be read.
     *
     * @return VirtualUsageData|null Used when it is a render pass method a template calls.
     */
    #[Override]
    protected function shouldMarkMethodAsUsed(ReflectionMethod $method): ?VirtualUsageData
    {
        return $method->getDeclaringClass()->getName() === RenderPass::class
            && array_key_exists($method->getName(), $this->reads()['methods'])
            ? VirtualUsageData::withNote('called by a template (#155)')
            : null;
    }

    /**
     * @param ReflectionClassConstant $constant A constant.
     *
     * @throws RuntimeException When a template cannot be read.
     *
     * @return VirtualUsageData|null Used when a template names it.
     */
    #[Override]
    protected function shouldMarkConstantAsUsed(ReflectionClassConstant $constant): ?VirtualUsageData
    {
        return $this->named($constant->getDeclaringClass()->getName(), $constant->getName());
    }

    /**
     * @param ReflectionEnumUnitCase $enumCase An enum case.
     *
     * @throws RuntimeException When a template cannot be read.
     *
     * @return VirtualUsageData|null Used when a template names it.
     */
    #[Override]
    protected function shouldMarkEnumCaseAsUsed(ReflectionEnumUnitCase $enumCase): ?VirtualUsageData
    {
        return $this->named($enumCase->getDeclaringClass()->getName(), $enumCase->getName());
    }

    /**
     * @param string $class A class.
     * @param string $name  A constant or case of it.
     *
     * @throws RuntimeException When a template cannot be read.
     *
     * @return VirtualUsageData|null Used when a template names `\Class::NAME`.
     */
    private function named(string $class, string $name): ?VirtualUsageData
    {
        return array_key_exists($class . '::' . $name, $this->reads()['constants'])
            ? VirtualUsageData::withNote('named by a template (#155)')
            : null;
    }

    /**
     * @throws RuntimeException When a template cannot be read.
     *
     * @return TemplateReads What the templates read.
     */
    private function reads(): array
    {
        if ($this->reads !== null) {
            return $this->reads;
        }

        $reads = ['properties' => [], 'methods' => [], 'constants' => []];

        foreach ($this->templates() as $template) {
            $source = file_get_contents($template);

            if ($source === false) {
                throw new RuntimeException('Cannot read the template ' . $template . '.');
            }

            $reads = $this->scan($source, $reads);
        }

        $this->reads = $reads;

        return $reads;
    }

    /**
     * @param string        $source A template.
     * @param TemplateReads $reads  What the templates read so far.
     *
     * @return TemplateReads With this template's reads.
     */
    private function scan(string $source, array $reads): array
    {
        $reads['properties'] += array_fill_keys(self::regexColumn('/->([A-Za-z_][A-Za-z0-9_]*)\\b(?!\\s*\\()/', $source, 1), true);
        $reads['methods'] += array_fill_keys(self::regexColumn('/->([A-Za-z_][A-Za-z0-9_]*)\\s*\\(/', $source, 1), true);

        foreach (self::regexRows('/(\\\\?[A-Z][A-Za-z0-9_\\\\]*)::([A-Za-z_][A-Za-z0-9_]*)/', $source) as $row) {
            if (!array_key_exists(1, $row) || !array_key_exists(2, $row)) {
                continue;
            }

            $reads['constants'][ltrim($row[1], '\\') . '::' . $row[2]] = true;
        }

        return $reads;
    }

    /**
     * @return list<string> Every `.phtml` under the read folders, nested ones included, from PHPStan's working
     *                      directory, the project's root.
     */
    private function templates(): array
    {
        $root = (string) getcwd();
        $templates = [];

        foreach (self::FOLDERS as $folder) {
            foreach (self::DEPTHS as $depth) {
                $templates = [...$templates, ...$this->glob($root . '/' . $folder . $depth)];
            }
        }

        return $templates;
    }

    /**
     * @param string $pattern A glob pattern.
     *
     * @return list<string> The files it matches; none when it cannot be read.
     */
    private function glob(string $pattern): array
    {
        $found = glob($pattern);

        return $found === false
            ? []
            : $found;
    }
}
