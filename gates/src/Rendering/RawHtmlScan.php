<?php

declare(strict_types=1);

namespace Gates\Rendering;

use Gates\Application\FileLister;
use Gates\Application\ProjectProfile;
use Gates\Application\QuietFileReader;
use Gates\Domain\GateProfile;
use Gates\Domain\RawHtmlUse;
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

use function array_filter;
use function array_values;
use function glob;
use function sort;
use function str_ends_with;
use function strlen;
use function substr;

use const GLOB_ONLYDIR;

/**
 * Walks the code RawHtml can sit in (gate:27, #99): `framework/src`, every
 * module's `src` and `generated`, each PHP file parsed with its names
 * resolved, so an alias or a grouped `use` is seen through.
 */
final readonly class RawHtmlScan
{
    /**
     * @param FileLister      $fileLister      Lists each root's files.
     * @param QuietFileReader $quietFileReader Reads each file.
     * @param ParserFactory   $parserFactory   Makes the parser.
     * @param ProjectProfile  $projectProfile  Whether the framework's source is the project's (#130).
     */
    public function __construct(
        private FileLister $fileLister = new FileLister(),
        private QuietFileReader $quietFileReader = new QuietFileReader(),
        private ParserFactory $parserFactory = new ParserFactory(),
        private ProjectProfile $projectProfile = new ProjectProfile(),
    ) {
    }

    /**
     * @param string $root The repo root, or a plant root.
     *
     * @throws Error When a file does not parse.
     *
     * @return array{list<RawHtmlUse>, list<RawHtmlUse>} Every `new RawHtml`, then every `RawHtml::` reference.
     */
    public function scan(string $root): array
    {
        $parser = $this->parserFactory->createForHostVersion();
        $uses = [];
        $references = [];

        foreach ($this->files($root) as $file) {
            $rawHtmlUseVisitor = new RawHtmlUseVisitor(substr($file, strlen($root) + 1));
            (new NodeTraverser(new NameResolver(), $rawHtmlUseVisitor))->traverse(
                $parser->parse($this->quietFileReader->read($file) ?? '') ?? [],
            );
            $uses = [...$uses, ...$rawHtmlUseVisitor->uses()];
            $references = [...$references, ...$rawHtmlUseVisitor->references()];
        }

        return [$uses, $references];
    }

    /**
     * @param string $root The root.
     *
     * @return list<string> Every PHP file under its roots, sorted.
     */
    private function files(string $root): array
    {
        $modules = glob($root . '/modules/*/src', GLOB_ONLYDIR);
        $themes = glob($root . '/themes/*', GLOB_ONLYDIR);
        $framework = $this->projectProfile->profile($root) === GateProfile::Aic
            ? [$root . '/framework/src']
            : [];
        $roots = [...$framework, ...($modules === false ? [] : $modules), ...($themes === false ? [] : $themes), $root . '/generated'];
        $files = [];

        foreach ($roots as $directory) {
            $files = [...$files, ...$this->fileLister->files($directory)];
        }

        $php = array_values(array_filter(
            $files,
            static fn (string $file): bool => str_ends_with($file, '.php') && !str_ends_with($file, '.tpl.php'),
        ));
        sort($php);

        return $php;
    }
}
