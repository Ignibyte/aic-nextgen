<?php

declare(strict_types=1);

namespace Gates\Templates;

use Gates\Domain\RegexCaptures;
use Gates\Domain\TemplateRefusal;
use Gates\Domain\TemplateViolation;
use PhpParser\Comment\Doc;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Nop;

use function array_key_exists;
use function basename;
use function count;
use function lcfirst;
use function sprintf;
use function str_starts_with;
use function substr;

/**
 * A template's typing line (#96, #36's convention): the file starts with.
 *
 * exactly `<?php /** @var \Fully\Qualified\Name $name *\/ ?>`. The class's short
 * name is the file's (`NoteList.html.php` gives `NoteList`) and the variable
 * its lower-camel form (`$noteList`), the component variable the template
 * reads. #97 resolves the class; #96 checks the shape.
 */
final readonly class TemplateHead
{
    use RegexCaptures;

    /**
     * The docblock: a fully qualified class and a variable.
     */
    private const string DOC = '/^\\/\\*\\* @var \\\\((?:[A-Za-z_][A-Za-z0-9_]*\\\\)*([A-Za-z_][A-Za-z0-9_]*))'
        . ' \\$([A-Za-z_][A-Za-z0-9_]*) \\*\\/$/';

    /**
     * @param string     $path   The template.
     * @param string     $source Its source.
     * @param list<Stmt> $stmts  Its statements; the first is marked as the typing line when it is one.
     *
     * @return TemplateViolation|string The component variable's name, or the refusal.
     */
    public function component(string $path, string $source, array $stmts): TemplateViolation|string
    {
        $short = substr(basename($path), 0, -9);
        $first = $stmts[0] ?? null;

        if (!$first instanceof Nop || !$this->typed($source, $this->doc($first), $short)) {
            return new TemplateViolation($path, 1, TemplateRefusal::ComponentType, sprintf(
                'the file starts with <?php /** @var \\Fully\\Qualified\\%s $%s */ ?>',
                $short,
                lcfirst($short),
            ));
        }

        $first->setAttribute(TemplateGrammarVisitor::HEAD, true);

        return lcfirst($short);
    }

    /**
     * @param list<Stmt> $stmts A template's statements, its typing line checked by `component()`.
     *
     * @return string The class the typing line names, fully qualified; empty when there is none.
     */
    public function componentClass(array $stmts): string
    {
        $first = $stmts[0] ?? null;

        if (!$first instanceof Nop) {
            return '';
        }

        $match = self::regexMatch(self::DOC, $this->doc($first));

        return $match[1] ?? '';
    }

    /**
     * @param Nop $nop The first statement.
     *
     * @return string Its one docblock's text; empty when it has another comment, or none.
     */
    private function doc(Nop $nop): string
    {
        $comments = $nop->getComments();
        $first = $comments[0] ?? null;

        return count($comments) === 1 && $first instanceof Doc
            ? $first->getText()
            : '';
    }

    /**
     * @param string $source The template's source.
     * @param string $doc    The first statement's docblock.
     * @param string $short  The file's class short name.
     *
     * @return bool Whether the file opens on the typing line and it names the file's class and variable.
     */
    private function typed(string $source, string $doc, string $short): bool
    {
        if (!str_starts_with($source, '<?php /** @var ')) {
            return false;
        }

        $match = self::regexMatch(self::DOC, $doc);

        return array_key_exists(2, $match) && array_key_exists(3, $match)
            && $match[2] === $short
            && $match[3] === lcfirst($short);
    }
}
