<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Workflow\Domain\Make\ManifestCall;

use function strtr;

/** Use the same module declaration seam as authored themes, with a reusable layout export. */
final readonly class MakeThemeCalls
{
    /** Contracts are source references; workflow has no runtime dependency on module types. */
    private const string CONTRACTS = 'AIC\\Module\\Contracts\\';

    /** Canonical authored constructor formatting, before the normal manifest editor places the chain. */
    private const string SELECTION = <<<'PHP'
        ->with(new ModuleTheme(
                '{name}',
                'layouts/html.tpl.php',
                'layouts/page.tpl.php',
                {class}::class,
                ['header' => 'header', 'content' => 'content', 'nav' => 'nav', 'footer' => 'footer'],
                [new ModuleAsset('base', 'assets/theme.css', ModuleAssetKind::Style)],
            ))
        PHP;

    /**
     * @param string $name  The theme identity.
     * @param string $class Its authored site page-data class.
     *
     * @return ManifestCall The explicit selection and exported usable service in one edit.
     */
    public function call(string $name, string $class): ManifestCall
    {
        $manifestCall = new ManifestCall(
            strtr(self::SELECTION, ['{name}' => $name, '{class}' => $class]),
            [self::CONTRACTS . 'ModuleTheme', self::CONTRACTS . 'ModuleAsset', self::CONTRACTS . 'ModuleAssetKind',
                'App\\Site\\Component\\' . $class],
        );

        return $manifestCall->then((new MakeCalls())->classOnly('export', 'Service', 'HtmlLayout', 'App\\Site'));
    }
}
