<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Workflow\Application\Scaffold\ScaffoldTemplate;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_map;
use function basename;
use function file_get_contents;
use function glob;
use function is_file;
use function sort;

/**
 * The built-in makes' text (#301): each a `*.tpl` file under the package's
 * `scaffolds/<kind>/`, in the grammar an app's scaffolds use (#298), filled
 * with the values the make's Source class computes. The kinds that compute
 * (an entity's fields, a migration's version, a page's component and layout,
 * the manifest edits) keep that work in PHP; only their text lives here.
 */
final readonly class MakeTemplates
{
    /**
     * @param string           $folder           The package's `scaffolds/` folder.
     * @param ScaffoldTemplate $scaffoldTemplate The grammar.
     */
    public function __construct(
        private string $folder = __DIR__ . '/../../../scaffolds',
        private ScaffoldTemplate $scaffoldTemplate = new ScaffoldTemplate(),
    ) {
    }

    /**
     * @param string                $kind     The built-in kind, such as `job`.
     * @param string                $template The template's file name, such as `job.php.tpl`.
     * @param array<string, string> $values   Every placeholder's value.
     *
     * @throws WorkflowRefusal When the template is missing or names a placeholder without a value.
     *
     * @return string The file's bytes.
     */
    public function render(string $kind, string $template, array $values): string
    {
        return $this->scaffoldTemplate->render($this->text($kind, $template), $values);
    }

    /**
     * @param string $kind     The built-in kind.
     * @param string $template The template's file name.
     *
     * @throws WorkflowRefusal When the template is missing.
     *
     * @return string Its text, unfilled (`make:scaffold --from`, #299).
     */
    public function text(string $kind, string $template): string
    {
        $path = $this->folder . '/' . $kind . '/' . $template;
        $text = is_file($path)
            ? file_get_contents($path)
            : false;

        if ($text === false) {
            throw new WorkflowRefusal('the workflow package lacks scaffolds/' . $kind . '/' . $template . '; reinstall it.');
        }

        return $text;
    }

    /**
     * @param string $kind A kind.
     *
     * @return list<string> The file names of its built-in templates, sorted; empty when it is no built-in (#299).
     */
    public function names(string $kind): array
    {
        $paths = glob($this->folder . '/' . $kind . '/*.tpl');
        $names = array_map(basename(...), $paths === false ? [] : $paths);
        sort($names);

        return $names;
    }
}
