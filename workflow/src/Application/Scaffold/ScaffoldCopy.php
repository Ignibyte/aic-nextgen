<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Scaffold;

use AIC\Workflow\Application\Make\MakeTemplates;
use AIC\Workflow\Domain\Make\MadeFile;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_keys;
use function array_map;
use function implode;
use function in_array;
use function preg_quote;
use function preg_replace;
use function sprintf;
use function str_ends_with;
use function str_replace;

/**
 * A new scaffold's files, started from a kind that exists (#299), so the
 * agent edits a known shape rather than starting blank: an app or AIC kind
 * is copied whole with its kind renamed and `from()` naming the source; a
 * built-in's templates are copied with `{class}` read as `{name}`, beside a
 * starting manifest that does not load until it describes itself, so
 * `make:catalog` tells the agent what is left to write.
 */
final readonly class ScaffoldCopy
{
    /**
     * A starting manifest: the kind, the built-in it starts from, then one `->file()` per template.
     */
    private const string MANIFEST = <<<'PHP'
        <?php

        declare(strict_types=1);

        /*
         * The %1$s scaffold, started by bin/aic make:scaffold from make:%2$s (#299). Describe it, set
         * each path, add its arguments, manifest edits and probe values; bin/aic make:catalog says what is missing.
         */

        use AIC\Workflow\Domain\Scaffold\ScaffoldManifest;

        return (new ScaffoldManifest('%1$s'))
            ->from('%2$s')
        %3$s
            ->probe(['name' => 'Probe']);

        PHP;

    /**
     * @param ScaffoldLoader $scaffoldLoader The app's and AIC's kinds.
     * @param ScaffoldSource $scaffoldSource Their files as written.
     * @param MakeTemplates  $makeTemplates  The built-ins' templates.
     */
    public function __construct(
        private ScaffoldLoader $scaffoldLoader,
        private ScaffoldSource $scaffoldSource,
        private MakeTemplates $makeTemplates = new MakeTemplates(),
    ) {
    }

    /**
     * @param string $kind The new kind.
     * @param string $from The kind it starts from.
     *
     * @throws WorkflowRefusal When the source is no kind, or does not load.
     *
     * @return list<MadeFile> The new folder's files, the manifest first.
     */
    public function files(string $kind, string $from): array
    {
        if (in_array($from, $this->scaffoldLoader->kinds(), true)) {
            return $this->copied($kind, $from);
        }

        $names = $this->makeTemplates->names($from);

        if ($names === []) {
            throw new WorkflowRefusal('--from=' . $from . ' is no kind: bin/aic make:catalog lists them.');
        }

        return $this->started($kind, $from, $names);
    }

    /**
     * @param string $kind The new kind.
     * @param string $from An app or AIC kind.
     *
     * @throws WorkflowRefusal When it does not load.
     *
     * @return list<MadeFile> Its manifest, renamed, and its templates.
     */
    private function copied(string $kind, string $from): array
    {
        $loadedScaffold = $this->scaffoldLoader->load($from);
        $manifest = $this->scaffoldSource->template($from, $from . '.scaffold.php') ?? '';
        $renamed = (string) preg_replace(
            "/new ScaffoldManifest\\('" . preg_quote($from, '/') . "'\\)\\)/",
            "new ScaffoldManifest('" . $kind . "'))",
            $manifest,
        );
        $sourced = (string) preg_replace("/->from\\('[^']*'\\)/", "->from('" . $from . "')", $renamed);
        $folder = 'scaffolds/' . $kind . '/';

        return [
            new MadeFile($folder . $kind . '.scaffold.php', $sourced),
            ...array_map(
                static fn (string $name): MadeFile => new MadeFile($folder . $name, $loadedScaffold->template($name)),
                array_keys($loadedScaffold->templates),
            ),
        ];
    }

    /**
     * @param string       $kind  The new kind.
     * @param string       $from  A built-in.
     * @param list<string> $names Its templates' file names.
     *
     * @throws WorkflowRefusal When a template cannot be read.
     *
     * @return list<MadeFile> A starting manifest and the templates.
     */
    private function started(string $kind, string $from, array $names): array
    {
        $folder = 'scaffolds/' . $kind . '/';
        $templates = array_map(
            fn (string $name): MadeFile => new MadeFile(
                $folder . $name,
                str_replace('{class}', '{name}', $this->makeTemplates->text($from, $name)),
            ),
            $names,
        );

        return [new MadeFile($folder . $kind . '.scaffold.php', self::manifest($kind, $from, $names)), ...$templates];
    }

    /**
     * @param string       $kind  The new kind.
     * @param string       $from  The built-in it starts from.
     * @param list<string> $names Its templates' file names.
     *
     * @return string A manifest that names each template and its probe, and does not load until it describes itself.
     */
    private static function manifest(string $kind, string $from, array $names): string
    {
        $files = array_map(
            static fn (string $name): string => "    ->file('" . self::path($name) . "', '" . $name . "')",
            $names,
        );

        return sprintf(self::MANIFEST, $kind, $from, implode("\n", $files));
    }

    /**
     * @param string $name A template's file name.
     *
     * @return string A first path for it, relative to the module, which the agent sets.
     */
    private static function path(string $name): string
    {
        return match (true) {
            str_ends_with($name, '.php.tpl') => 'src/{name}.php',
            str_ends_with($name, '.phtml.tpl') => '{name|kebab}.phtml',
            default => '{name|kebab}.txt',
        };
    }
}
