<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Component\Contracts\Component;
use Gates\Domain\TemplateBinding;
use Gates\Domain\TemplateRole;
use InvalidArgumentException;

use function array_fill_keys;
use function array_intersect_key;
use function get_object_vars;
use function preg_match;
use function strtolower;

/** The fixed library's template identities, independent of module ownership and source discovery. */
final readonly class AtomCatalog
{
    /**
     * @param TextAtoms    $textAtoms    Text and resource declarations.
     * @param ControlAtoms $controlAtoms Control declarations.
     * @param FormAtoms    $formAtoms    Form declarations, with inline-child atoms in the terminal chunk.
     */
    public function __construct(
        private TextAtoms $textAtoms = new TextAtoms(),
        private ControlAtoms $controlAtoms = new ControlAtoms(),
        private FormAtoms $formAtoms = new FormAtoms(),
    ) {
    }

    /**
     * @throws InvalidArgumentException When two declarations claim the same identity.
     *
     * @return array<string, TemplateBinding> Explicit library bindings keyed by authored path, in lookup order.
     */
    public function bindings(): array
    {
        $bindings = [];
        $claimed = [];
        $declarations = [...$this->textAtoms->declarations(), ...$this->controlAtoms->declarations(), ...$this->formAtoms->declarations()];

        foreach ($declarations as [$component, $source]) {
            $templateBinding = $this->binding($component, $source);
            $identities = $this->identities($templateBinding);

            if (array_intersect_key($claimed, $identities) !== []) {
                throw new InvalidArgumentException('Duplicate library component, source or compiled output identity.');
            }

            $claimed += $identities;
            $bindings[$templateBinding->source] = $templateBinding;
        }

        return $bindings;
    }

    /**
     * @param TemplateBinding $templateBinding A claimed library identity.
     *
     * @throws InvalidArgumentException When the fixed catalog has duplicate claims.
     *
     * @return bool Whether every declared identity exactly matches the fixed library catalog.
     */
    public function contains(TemplateBinding $templateBinding): bool
    {
        $expected = $this->bindings()[$templateBinding->source] ?? null;

        return $expected !== null && get_object_vars($expected) === get_object_vars($templateBinding);
    }

    /**
     * @param class-string<Component> $component The declared input identity.
     * @param string                  $source    The declared source basename, lower kebab case.
     *
     * @throws InvalidArgumentException When the source basename is not a portable template name.
     *
     * @return TemplateBinding Its library-owned source, its escaped output beside the atom lookups, and the inline
     *                         role.
     */
    private function binding(string $component, string $source): TemplateBinding
    {
        if (preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*\\z/', $source) !== 1) {
            throw new InvalidArgumentException('Declare a portable atom source name: lower case words joined by hyphens.');
        }

        return new TemplateBinding(
            $component,
            'framework/templates/components/' . $source . '.phtml',
            'framework/src/Component/Application/Templates/' . $source . '.phtml',
            TemplateRole::Atom,
        );
    }

    /**
     * @param TemplateBinding $templateBinding A fixed library declaration.
     *
     * @return array<string, bool> Its independently unique claims.
     */
    private function identities(TemplateBinding $templateBinding): array
    {
        return array_fill_keys([
            'component:' . strtolower($templateBinding->component),
            'source:' . $templateBinding->source,
            'output:' . $templateBinding->generated,
        ], true);
    }
}
