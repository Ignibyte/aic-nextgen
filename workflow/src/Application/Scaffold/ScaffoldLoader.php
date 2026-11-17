<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Scaffold;

use AIC\Workflow\Domain\Scaffold\LoadedScaffold;
use AIC\Workflow\Domain\Scaffold\ScaffoldFile;
use AIC\Workflow\Domain\Scaffold\ScaffoldManifest;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_map;
use function array_unique;
use function count;
use function in_array;
use function preg_match;

/**
 * Loads one app scaffold and checks every part before a make may use it
 * (#298), so a broken scaffold is refused at load, never half-written: its
 * kind and folder, its description, each argument, each file's path pattern
 * and template, each placeholder and filter in the paths, the templates and
 * the manifest edits, each edit's shape, and the probe values.
 */
final readonly class ScaffoldLoader
{
    /**
     * The kinds the built-in makes and the engine's own commands use, which no app scaffold may take.
     */
    public const array RESERVED = [
        'api', 'block', 'catalog', 'command', 'component', 'entity', 'job', 'layout', 'migration', 'module', 'page',
        'policy', 'scaffold', 'theme',
    ];

    /**
     * @param ScaffoldSource $scaffoldSource The app's scaffolds.
     * @param ScaffoldChecks $scaffoldChecks The checks on arguments, the probe and placeholders.
     * @param ScaffoldEdits  $scaffoldEdits  The manifest edits' shapes.
     */
    public function __construct(
        private ScaffoldSource $scaffoldSource,
        private ScaffoldChecks $scaffoldChecks = new ScaffoldChecks(),
        private ScaffoldEdits $scaffoldEdits = new ScaffoldEdits(),
    ) {
    }

    /**
     * @return list<string> The kinds under `scaffolds/`.
     */
    public function kinds(): array
    {
        return $this->scaffoldSource->kinds();
    }

    /**
     * @param string $kind A kind.
     *
     * @throws WorkflowRefusal When there is no such scaffold or any part of it breaks a rule.
     *
     * @return LoadedScaffold The manifest and its templates.
     */
    public function load(string $kind): LoadedScaffold
    {
        $scaffoldManifest = $this->manifest($kind);
        $folder = $this->scaffoldSource->folder($kind);
        $where = $folder . $kind . '.scaffold.php';
        $known = $this->scaffoldChecks->args($where, $scaffoldManifest);
        $templates = $this->files($folder, $scaffoldManifest, $known);

        foreach ($scaffoldManifest->edits as $edit) {
            $this->scaffoldEdits->check($where, $edit);

            foreach ($edit->values as $value) {
                $this->scaffoldChecks->known($where . ': ->' . $edit->method . '()', $value, $known);
            }
        }

        $this->scaffoldChecks->probe($where, $scaffoldManifest);

        return new LoadedScaffold($scaffoldManifest, $templates, $folder);
    }

    /**
     * @param string $kind A kind.
     *
     * @throws WorkflowRefusal When the kind breaks the rule or is a built-in's, or the manifest is missing, names another
     *                         kind or describes nothing.
     *
     * @return ScaffoldManifest Its manifest.
     */
    private function manifest(string $kind): ScaffoldManifest
    {
        if (preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/', $kind) !== 1) {
            throw new WorkflowRefusal('make takes a kind first, lower case words joined by -; bin/aic make:catalog lists them.');
        }

        if (in_array($kind, self::RESERVED, true)) {
            throw new WorkflowRefusal('the kind ' . $kind . ' is a built-in make: run bin/aic make:' . $kind . '.');
        }

        $scaffoldManifest = $this->scaffoldSource->manifest($kind);
        $where = $this->scaffoldSource->folder($kind) . $kind . '.scaffold.php';

        return match (true) {
            $scaffoldManifest->kind !== $kind => throw new WorkflowRefusal(
                $where . ' names its kind ' . $scaffoldManifest->kind . ', not ' . $kind . '.',
            ),
            $scaffoldManifest->description === '' => throw new WorkflowRefusal(
                $where . ' has no ->describe(...): the catalog tells the agent what it makes.',
            ),
            default => $scaffoldManifest,
        };
    }

    /**
     * @param string           $folder           The scaffold's folder as shown, for the refusal.
     * @param ScaffoldManifest $scaffoldManifest The manifest.
     * @param list<string>     $known            The placeholders it may use.
     *
     * @throws WorkflowRefusal When it writes no file or one twice, or a path or template breaks a rule.
     *
     * @return array<string, string> Each template's text, by file name.
     */
    private function files(string $folder, ScaffoldManifest $scaffoldManifest, array $known): array
    {
        $where = $folder . $scaffoldManifest->kind . '.scaffold.php';
        $paths = array_map(static fn (ScaffoldFile $scaffoldFile): string => $scaffoldFile->path, $scaffoldManifest->files);

        if ($paths === [] || count(array_unique($paths)) !== count($paths)) {
            throw new WorkflowRefusal($where . ' writes each file once, and at least one: ->file(<path>, <template>).');
        }

        $templates = [];

        foreach ($scaffoldManifest->files as $file) {
            $templates[$file->template] = $this->file($folder, $scaffoldManifest->kind, $file, $known);
        }

        return $templates;
    }

    /**
     * @param string       $folder       The scaffold's folder as shown, for the refusal.
     * @param string       $kind         The kind.
     * @param ScaffoldFile $scaffoldFile A file it writes.
     * @param list<string> $known        The placeholders it may use.
     *
     * @throws WorkflowRefusal When the path leaves the module folder, or the template is not a `*.tpl` beside the
     *                         manifest, or either names an unknown placeholder or filter.
     *
     * @return string The template's text.
     */
    private function file(string $folder, string $kind, ScaffoldFile $scaffoldFile, array $known): string
    {
        $where = $folder . $kind . '.scaffold.php';

        if (preg_match('#^(?!/)(?!.*(^|/)\\.\\.?(/|$))[A-Za-z0-9_.{}|/-]+$#', $scaffoldFile->path) !== 1) {
            throw new WorkflowRefusal($where . ': the path ' . $scaffoldFile->path . ' must stay inside the module folder.');
        }

        $template = preg_match('/^[a-z0-9][a-z0-9._-]*\\.tpl$/', $scaffoldFile->template) === 1
            ? $this->scaffoldSource->template($kind, $scaffoldFile->template)
            : null;
        $text = $template ?? throw new WorkflowRefusal(
            $where . ' names ' . $scaffoldFile->template . ', which is not a *.tpl file in its folder.',
        );
        $this->scaffoldChecks->known($where . ': ' . $scaffoldFile->path, $scaffoldFile->path, $known);
        $this->scaffoldChecks->known($folder . $scaffoldFile->template, $text, $known);

        return $text;
    }
}
