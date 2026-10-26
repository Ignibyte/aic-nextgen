<?php

declare(strict_types=1);

namespace Gates\Markup;

use Gates\Domain\MarkupFinding;
use Override;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\InlineHTML;
use PhpParser\NodeVisitorAbstract;

use function array_key_exists;
use function in_array;
use function spl_object_id;
use function strtolower;

/**
 * Reads a module PHP file's literal text for gate:32 (#156). Every literal form folds to one text: a concat chain left
 * to right, an interpolated string by its parts, each value known only at run time a hole. Inline output outside
 * `<?php` and the HTML functions count too. Comments are not read: they are not output.
 */
final class LiteralTextVisitor extends NodeVisitorAbstract
{
    /** The functions that escape, unescape or strip markup: the Renderer's job, never a module's. */
    private const array FUNCTIONS = [
        'htmlspecialchars', 'htmlspecialchars_decode', 'htmlentities', 'html_entity_decode', 'get_html_translation_table',
        'strip_tags', 'nl2br',
    ];

    /** @var list<MarkupFinding> What it found. */
    private array $findings = [];

    /** @var array<int, true> The literals a concat chain already read, by object id. */
    private array $folded = [];

    /**
     * @param string     $path       The file, relative to the root.
     * @param bool       $controller Whether it is a controller, where any `<` counts.
     * @param MarkupText $markupText What counts as HTML.
     */
    public function __construct(
        private readonly string $path,
        private readonly bool $controller,
        private readonly MarkupText $markupText = new MarkupText(),
    ) {
    }

    /**
     * @param Node $node A node, outermost first.
     */
    #[Override]
    public function enterNode(Node $node): null
    {
        if (array_key_exists(spl_object_id($node), $this->folded)) {
            return null;
        }

        if ($node instanceof InlineHTML) {
            $this->add($node, 'it writes output outside <?php');
        }

        if ($node instanceof FuncCall) {
            $this->call($node);
        }

        if ($node instanceof Concat || $node instanceof String_ || $node instanceof InterpolatedString) {
            $this->text($node, $this->fold($node));
        }

        return null;
    }

    /**
     * @return list<MarkupFinding> What it found.
     */
    public function findings(): array
    {
        return $this->findings;
    }

    /**
     * @param Expr $expr A literal, a concat chain or any other value.
     *
     * @return string Its text: a literal's value, a chain's leaves left to right, a hole for anything else.
     */
    private function fold(Expr $expr): string
    {
        $this->folded[spl_object_id($expr)] = true;

        return match (true) {
            $expr instanceof Concat => $this->fold($expr->left) . $this->fold($expr->right),
            $expr instanceof String_ => $this->literal($expr),
            $expr instanceof InterpolatedString => $this->parts($expr),
            default => MarkupText::HOLE,
        };
    }

    /**
     * @param InterpolatedString $interpolatedString A double-quoted or heredoc string with values in it.
     *
     * @return string Its text, each value a hole.
     */
    private function parts(InterpolatedString $interpolatedString): string
    {
        $text = '';

        foreach ($interpolatedString->parts as $part) {
            $text .= $part instanceof InterpolatedStringPart
                ? $part->value
                : MarkupText::HOLE;
        }

        return $text;
    }

    /**
     * @param String_ $string A string literal.
     *
     * @return string Its value, after the check that it names no HTML function as a callable.
     */
    private function literal(String_ $string): string
    {
        if (in_array(strtolower($string->value), self::FUNCTIONS, true)) {
            $this->add($string, 'a string names the HTML function ' . $string->value);
        }

        return $string->value;
    }

    /**
     * @param Node   $node The text's node.
     * @param string $text Its folded text.
     */
    private function text(Node $node, string $text): void
    {
        $found = $this->markupText->find($text, true, $this->controller);

        if ($found === null) {
            return;
        }

        $this->add($node, 'a string holds ' . $found);
    }

    /**
     * @param FuncCall $funcCall A function call.
     */
    private function call(FuncCall $funcCall): void
    {
        if (
            !($funcCall->name instanceof Name)
            || !in_array(strtolower($funcCall->name->toString()), self::FUNCTIONS, true)
        ) {
            return;
        }

        $this->add($funcCall, 'it calls the HTML function ' . $funcCall->name->toString());
    }

    /**
     * @param Node   $node Where it is.
     * @param string $what What it is.
     */
    private function add(Node $node, string $what): void
    {
        $this->findings[] = new MarkupFinding($this->path, $node->getStartLine(), $what);
    }
}
