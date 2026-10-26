<?php

declare(strict_types=1);

namespace Gates\Markup;

use Gates\Application\QuietFileReader;
use Gates\Domain\MarkupFinding;
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

use function explode;
use function preg_match;
use function str_contains;
use function str_ends_with;
use function strtolower;

/**
 * One file under `modules/` for gate:32 (#156): a template or markup file fails by its name; PHP is parsed and its
 * literal text read; any other text file is read raw; a binary file passes on its name.
 */
final readonly class FileMarkupCheck
{
    /** The file types that are templates or markup, whatever they hold. */
    private const array TYPES = [
        '.tpl.php', '.html.php', '.blade.php', '.phtml', '.html', '.htm', '.xhtml', '.shtml', '.twig', '.latte', '.mustache',
        '.hbs', '.handlebars', '.tpl', '.svg', '.vue', '.svelte',
    ];

    /**
     * @param QuietFileReader $quietFileReader Reads each file.
     * @param ParserFactory   $parserFactory   Makes the PHP parser.
     * @param MarkupText      $markupText      What counts as HTML.
     */
    public function __construct(
        private QuietFileReader $quietFileReader = new QuietFileReader(),
        private ParserFactory $parserFactory = new ParserFactory(),
        private MarkupText $markupText = new MarkupText(),
    ) {
    }

    /**
     * @param string $root     The root.
     * @param string $relative A file, relative to it.
     *
     * @return list<MarkupFinding> What it holds that is markup.
     */
    public function check(string $root, string $relative): array
    {
        foreach (self::TYPES as $type) {
            if (str_ends_with(strtolower($relative), $type)) {
                return [new MarkupFinding($relative, 0, 'a ' . $type . ' file is markup')];
            }
        }

        $contents = $this->quietFileReader->read($root . '/' . $relative);

        return match (true) {
            $contents === null => [new MarkupFinding($relative, 0, 'the file cannot be read')],
            str_ends_with($relative, '.php') => $this->php($relative, $contents),
            str_contains($contents, "\0") => [],
            default => $this->text($relative, $contents),
        };
    }

    /**
     * @param string $relative The file.
     * @param string $contents Its PHP.
     *
     * @return list<MarkupFinding> Its inline output, HTML in its literal text and HTML function calls.
     */
    private function php(string $relative, string $contents): array
    {
        try {
            $statements = $this->parserFactory->createForHostVersion()->parse($contents) ?? [];
        } catch (Error $error) {
            return [new MarkupFinding($relative, 0, 'it does not parse as PHP (' . $error->getRawMessage() . ')')];
        }

        $literalTextVisitor = new LiteralTextVisitor($relative, preg_match('#^modules/[^/]+/src/Controller/#', $relative) === 1);
        (new NodeTraverser(new NameResolver(), $literalTextVisitor))->traverse($statements);

        return $literalTextVisitor->findings();
    }

    /**
     * @param string $relative The file.
     * @param string $contents Its text.
     *
     * @return list<MarkupFinding> One finding per line that holds a tag, a markup declaration or a character reference.
     */
    private function text(string $relative, string $contents): array
    {
        $findings = [];

        foreach (explode("\n", $contents) as $index => $line) {
            $found = $this->markupText->find($line, false, false);

            if ($found === null) {
                continue;
            }

            $findings[] = new MarkupFinding($relative, $index + 1, 'the text holds ' . $found);
        }

        return $findings;
    }
}
