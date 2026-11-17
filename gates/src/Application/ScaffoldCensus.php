<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Workflow\Application\Make\MakeTemplates;
use AIC\Workflow\Application\Scaffold\ScaffoldLoader;
use AIC\Workflow\Application\Scaffold\ScaffoldSource;
use AIC\Workflow\Application\Scaffold\ScaffoldTemplate;
use AIC\Workflow\Application\Scaffold\ScaffoldValues;
use AIC\Workflow\Domain\Scaffold\ScaffoldArg;
use AIC\Workflow\Domain\Scaffold\ScaffoldFile;
use AIC\Workflow\Domain\Scaffold\ScaffoldManifest;
use AIC\Workflow\Exception\WorkflowRefusal;
use Throwable;

use function array_diff;
use function array_map;
use function array_values;
use function implode;
use function in_array;
use function str_ends_with;
use function strlen;
use function substr;

/**
 * gate:33's census of one scaffolds folder (#300, after AICL's
 * `ScaffoldCensus`): per kind, a manifest that does not load (`load`), a
 * `from()` that names no kind (`from`), a file in the folder no manifest
 * names (`stray`) and a placeholder in a template no argument fills
 * (`placeholder`). Each rule reads the folder on its own, so one broken part
 * does not hide another.
 */
final readonly class ScaffoldCensus
{
    /**
     * The rules, in the order the gate reports them.
     */
    public const array RULES = ['load', 'from', 'stray', 'placeholder'];

    /**
     * @param MakeTemplates    $makeTemplates    The built-ins, which a `from()` may name.
     * @param ScaffoldTemplate $scaffoldTemplate The template grammar.
     * @param FileLister       $fileLister       Lists a kind's folder.
     */
    public function __construct(
        private MakeTemplates $makeTemplates = new MakeTemplates(),
        private ScaffoldTemplate $scaffoldTemplate = new ScaffoldTemplate(),
        private FileLister $fileLister = new FileLister(),
    ) {
    }

    /**
     * @param ScaffoldLoader $scaffoldLoader Every kind there is, which a `from()` may name.
     * @param ScaffoldSource $scaffoldSource The folder's kinds.
     * @param string         $folder         The folder, absolute.
     *
     * @return array<string, list<string>> Each rule's findings, every rule present.
     */
    public function findings(ScaffoldLoader $scaffoldLoader, ScaffoldSource $scaffoldSource, string $folder): array
    {
        $findings = ['from' => [], 'load' => [], 'placeholder' => [], 'stray' => []];

        foreach ($scaffoldSource->kinds() as $kind) {
            $findings['load'] = [...$findings['load'], ...$this->load($scaffoldLoader, $kind)];
            $manifest = self::manifest($scaffoldSource, $kind);

            if (!$manifest instanceof ScaffoldManifest) {
                continue;
            }

            $files = $this->files($folder . '/' . $kind);
            $findings['from'] = [...$findings['from'], ...$this->from($scaffoldLoader, $manifest)];
            $findings['stray'] = [...$findings['stray'], ...self::stray($manifest, $files)];
            $findings['placeholder'] = [...$findings['placeholder'], ...$this->placeholders($scaffoldSource, $manifest, $files)];
        }

        return $findings;
    }

    /**
     * @param ScaffoldLoader $scaffoldLoader The loader.
     * @param string         $kind           A kind.
     *
     * @return list<string> Why it does not load; empty when it does.
     */
    private function load(ScaffoldLoader $scaffoldLoader, string $kind): array
    {
        try {
            $scaffoldLoader->load($kind);
        } catch (WorkflowRefusal $workflowRefusal) {
            return [$kind . ' does not load: ' . $workflowRefusal->getMessage()];
        }

        return [];
    }

    /**
     * @param ScaffoldLoader   $scaffoldLoader   Every kind there is.
     * @param ScaffoldManifest $scaffoldManifest A manifest.
     *
     * @return list<string> Its `from()` when it names neither a scaffold kind nor a built-in.
     */
    private function from(ScaffoldLoader $scaffoldLoader, ScaffoldManifest $scaffoldManifest): array
    {
        $from = $scaffoldManifest->from;
        $named = $from === ''
            || in_array($from, $scaffoldLoader->kinds(), true)
            || $this->makeTemplates->names($from) !== [];

        return $named
            ? []
            : [$scaffoldManifest->kind . " names ->from('" . $from . "'), which is no kind."];
    }

    /**
     * @param ScaffoldSource   $scaffoldSource   The folder's kinds, which read each template.
     * @param ScaffoldManifest $scaffoldManifest A manifest.
     * @param list<string>     $files            Its folder's files, relative to the folder.
     *
     * @return list<string> Each placeholder in a template there that is neither built in nor an argument.
     */
    private function placeholders(
        ScaffoldSource $scaffoldSource,
        ScaffoldManifest $scaffoldManifest,
        array $files,
    ): array {
        $args = array_map(static fn (ScaffoldArg $scaffoldArg): string => $scaffoldArg->name, $scaffoldManifest->args);
        $known = [...ScaffoldValues::BUILT_IN, ...$args];
        $findings = [];

        foreach ($files as $file) {
            $text = str_ends_with($file, '.tpl')
                ? $scaffoldSource->template($scaffoldManifest->kind, $file) ?? ''
                : '';
            $names = array_map(static fn (array $found): string => $found[0], $this->scaffoldTemplate->placeholders($text));
            $unknown = array_diff($names, $known);
            $where = $scaffoldManifest->kind . '/' . $file;
            $findings = $unknown === []
                ? $findings
                : [...$findings, $where . ' uses {' . implode('}, {', $unknown) . '}, which no argument fills.'];
        }

        return $findings;
    }

    /**
     * @param string $folder A kind's folder, absolute.
     *
     * @return list<string> Its files, relative to it.
     */
    private function files(string $folder): array
    {
        return array_map(
            static fn (string $path): string => substr($path, strlen($folder) + 1),
            $this->fileLister->files($folder),
        );
    }

    /**
     * @param ScaffoldSource $scaffoldSource The folder's kinds.
     * @param string         $kind           A kind.
     *
     * @return ScaffoldManifest|null What its file returns; null when the file cannot be read as one.
     */
    private static function manifest(ScaffoldSource $scaffoldSource, string $kind): ?ScaffoldManifest
    {
        try {
            return $scaffoldSource->manifest($kind);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param ScaffoldManifest $scaffoldManifest A manifest.
     * @param list<string>     $files            Its folder's files, relative to the folder.
     *
     * @return list<string> Each file there that is neither the manifest nor a template it names.
     */
    private static function stray(ScaffoldManifest $scaffoldManifest, array $files): array
    {
        $named = [
            $scaffoldManifest->kind . '.scaffold.php',
            ...array_map(static fn (ScaffoldFile $scaffoldFile): string => $scaffoldFile->template, $scaffoldManifest->files),
        ];

        return array_values(array_map(
            static fn (string $file): string => $scaffoldManifest->kind . '/' . $file . ' is named by no ->file() of its manifest.',
            array_diff($files, $named),
        ));
    }
}
