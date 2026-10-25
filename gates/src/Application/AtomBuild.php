<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Component\Contracts\Component;
use Gates\Domain\GateProfile;
use Gates\Domain\TemplateBinding;
use Gates\Domain\TemplateClass;
use Gates\Domain\TemplateReader;
use InvalidArgumentException;
use ReflectionClass;

use function array_chunk;
use function array_diff;
use function array_keys;
use function count;
use function file_get_contents;
use function implode;
use function is_array;
use function json_decode;
use function realpath;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;

/** Plan the source package's compiled atoms; an installed app consumes its immutable runtime classes. */
final readonly class AtomBuild
{
    /**
     * @param TemplateReader       $templateReader       The strict reader, supplied by the composition root.
     * @param string               $root                 The actual project folder.
     * @param AtomCatalog          $atomCatalog          Fixed declarations, never filesystem selection.
     * @param TemplateCompiler     $templateCompiler     The same typed compiler used for app templates.
     * @param TemplateLookupSource $templateLookupSource Shared bounded lookup emission.
     */
    public function __construct(
        private TemplateReader $templateReader,
        private string $root,
        private AtomCatalog $atomCatalog = new AtomCatalog(),
        private TemplateCompiler $templateCompiler = new TemplateCompiler(),
        private TemplateLookupSource $templateLookupSource = new TemplateLookupSource(),
    ) {
    }

    /**
     * @throws InvalidArgumentException When the source package, canonical classes or authored paths are not owned.
     *
     * @return array<string, TemplateBinding> Checked source-owned declarations; none for the App profile.
     */
    public function bindings(): array
    {
        if ((new ProjectProfile())->profile($this->root) !== GateProfile::Aic) {
            return [];
        }

        $this->sourcePackage();
        $bindings = $this->atomCatalog->bindings();
        $declaredPaths = new DeclaredPaths($this->root);

        foreach ($bindings as $binding) {
            $declaredPaths->file('framework/templates/components', substr($binding->source, strlen('framework/templates/components/')));
            $this->component($binding);
        }

        $unclaimed = array_diff($declaredPaths->templates('framework/templates'), array_keys($bindings));

        if ($unclaimed !== []) {
            throw new InvalidArgumentException('Undeclared library templates: ' . implode(', ', $unclaimed) . '.');
        }

        return $bindings;
    }

    /**
     * @throws InvalidArgumentException When library declarations or any source cannot be compiled.
     *
     * @return array<string, string> Every package-owned output, including its lookup; none in an App.
     */
    public function files(): array
    {
        $files = [];
        $components = [];
        $templates = [];

        foreach ($this->bindings() as $templateBinding) {
            $source = file_get_contents($this->root . '/' . $templateBinding->source);

            if ($source === false) {
                throw new InvalidArgumentException($templateBinding->source . ': cannot read the declared library template.');
            }

            $templateClass = $this->compile($templateBinding, $source);
            $files[$this->root . '/' . $templateBinding->generated] = $templateClass->source;
            $components[$templateBinding->component] = $templateClass->takesPass;
            $templates[$templateBinding->component] = $templateBinding->templateClass;
        }

        return $files + ($components === [] ? [] : $this->lookup($components, $templates));
    }

    /**
     * @param TemplateBinding $templateBinding One fixed source identity.
     * @param string          $source          Its actual authored bytes.
     *
     * @throws InvalidArgumentException When reading or compilation refuses the source.
     *
     * @return TemplateClass Its compiled source and render-pass requirement.
     */
    private function compile(TemplateBinding $templateBinding, string $source): TemplateClass
    {
        try {
            $templateUnit = $this->templateReader->read($templateBinding->source, $source, $templateBinding);

            return $this->templateCompiler->compile($templateUnit->nodes, $templateBinding);
        } catch (InvalidArgumentException $invalidArgumentException) {
            $message = $invalidArgumentException->getMessage();

            throw new InvalidArgumentException(str_starts_with($message, $templateBinding->source . ':')
                ? $message : $templateBinding->source . ':' . $message, 0, $invalidArgumentException);
        }
    }

    /**
     * @throws InvalidArgumentException When the active runtime package is not this source checkout.
     */
    private function sourcePackage(): void
    {
        $path = (new DeclaredPaths($this->root))->file('framework', 'composer.json', '');
        $package = [json_decode((string) file_get_contents($this->root . '/' . $path), true)];
        $file = (new ReflectionClass(Component::class))->getFileName();
        $expected = $this->root . '/framework/src/Component/Contracts/Component.php';

        if (
            !is_array($package[0]) || ($package[0]['name'] ?? null) !== 'aic/framework'
            || $file === false || $expected !== realpath($file)
        ) {
            throw new InvalidArgumentException('Library compilation requires the actual local aic/framework source package.');
        }
    }

    /**
     * @param TemplateBinding $templateBinding One fixed atom declaration.
     *
     * @throws InvalidArgumentException When a loaded component has a foreign source or noncanonical identity.
     */
    private function component(TemplateBinding $templateBinding): void
    {
        $reflectionClass = new ReflectionClass($templateBinding->component);
        $expected = $this->root . '/framework/src/' . str_replace('\\', '/', substr($templateBinding->component, 4)) . '.php';
        $file = $reflectionClass->getFileName();

        if (
            !$reflectionClass->isFinal() || $reflectionClass->getName() !== $templateBinding->component
            || $file === false || realpath($file) !== $expected
        ) {
            throw new InvalidArgumentException($templateBinding->source
                . ': a library template renders only its canonical package-owned atom.');
        }
    }

    /**
     * @param array<string, bool>   $components Each fixed component's render-pass requirement.
     * @param array<string, string> $templates  Each exact compiled class.
     *
     * @throws InvalidArgumentException When the lookup source cannot fit normal limits.
     *
     * @return array<string, string> The package head and bounded chain parts.
     */
    private function lookup(array $components, array $templates): array
    {
        $namespace = 'AIC\\Component\\Application\\Templates';
        $directory = $this->root . '/framework/src/Component/Application/Templates/';
        $chunks = array_chunk($components, 4, true);
        $files = $this->head();

        foreach ($chunks as $index => $chunk) {
            $class = 'AtomTemplatesPart' . ($index + 1);
            $next = $index + 1 < count($chunks)
                ? $namespace . '\\AtomTemplatesPart' . ($index + 2)
                : null;
            $files[$directory . $class . '.php'] = $this->templateLookupSource->part(
                $namespace,
                $class,
                $chunk,
                $templates,
                $next,
                'framework/templates/components',
                'the declared library atoms.',
            );
        }

        return $files;
    }

    /**
     * @throws InvalidArgumentException When the head cannot fit the normal class limits.
     *
     * @return array<string, string> The package-owned head pointing at its compiled chain.
     */
    private function head(): array
    {
        $namespace = 'AIC\\Component\\Application\\Templates';

        return [$this->root . '/framework/src/Component/Application/AtomTemplates.php' => $this->templateLookupSource->head(
            'AIC\\Component\\Application',
            'AtomTemplates',
            $namespace . '\\AtomTemplatesPart1',
            'framework/templates/components',
            'Its markup, through the compiled library atom templates.',
        )];
    }
}
