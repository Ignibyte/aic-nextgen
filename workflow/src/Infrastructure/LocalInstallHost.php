<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\InstallHost;
use AIC\Workflow\Application\RunHost;
use AIC\Workflow\Domain\HouseRule;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;

use function array_map;
use function implode;
use function is_file;

/**
 * The project around the pack, on disk (#105): the operator's terminal from
 * the run host, an open run from `var/workflow/run.json`, composer.json's
 * declaration, and the settings through SettingsFile, as the engine reads
 * them.
 */
final readonly class LocalInstallHost implements InstallHost
{
    /**
     * What the settings say of an empty site.
     */
    private const string NO_SITE = '(none: a run that declares a GET route holds at check until it is set)';

    /**
     * @param string           $root             The project's root.
     * @param RunHost          $runHost          The operator's terminal.
     * @param ComposerManifest $composerManifest composer.json.
     * @param SettingsFile     $settingsFile     `config/workflow.php`.
     */
    public function __construct(
        private string $root,
        private RunHost $runHost,
        private ComposerManifest $composerManifest,
        private SettingsFile $settingsFile = new SettingsFile(),
    ) {
    }

    /**
     * @return bool Whether standard input is a terminal.
     */
    #[Override]
    public function operatorTerminal(): bool
    {
        return $this->runHost->operatorTerminal();
    }

    /**
     * @return bool Whether `var/workflow/run.json` exists.
     */
    #[Override]
    public function runOpen(): bool
    {
        return is_file($this->root . '/var/workflow/run.json');
    }

    /**
     * @return string composer.json's problem; empty when there is none.
     */
    #[Override]
    public function composerProblem(): string
    {
        return $this->composerManifest->problem();
    }

    /**
     * @throws WorkflowRefusal When `config/workflow.php` is there and does not load.
     *
     * @return string The settings' ticket directory; `tickets` before there are settings.
     */
    #[Override]
    public function tickets(): string
    {
        return is_file($this->root . '/config/workflow.php')
            ? $this->settingsFile->read($this->root)->tickets
            : 'tickets';
    }

    /**
     * @throws WorkflowRefusal When `config/workflow.php` does not load.
     *
     * @return list<string> The settings, a line each.
     */
    #[Override]
    public function settings(): array
    {
        $workflowSettings = $this->settingsFile->read($this->root);
        $rules = array_map(static fn (HouseRule $houseRule): string => $houseRule->id, $workflowSettings->houseRules);

        return [
            'config/workflow.php loads; the engine reads:',
            '  tickets       ' . $workflowSettings->tickets . '/',
            '  site          ' . ($workflowSettings->site === '' ? self::NO_SITE : $workflowSettings->site),
            '  gate retries  ' . $workflowSettings->maxGateRetries . ' per gate per phase; gate timeout '
                . $workflowSettings->gateTimeout . ' s',
            '  enforcement   ' . $workflowSettings->enforcement->value . '; require a run: ' . $workflowSettings->requireRun->value,
            '  house rules   ' . ($rules === [] ? '(none)' : implode(', ', $rules)),
            '  toolchain     ' . ($workflowSettings->toolchain === [] ? '(none)' : implode(', ', $workflowSettings->toolchain)),
        ];
    }
}
