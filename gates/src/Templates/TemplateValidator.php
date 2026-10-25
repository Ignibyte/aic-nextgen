<?php

declare(strict_types=1);

namespace Gates\Templates;

use Gates\Application\AtomCatalog;
use Gates\Domain\TemplateBinding;
use Gates\Domain\TemplateRefusal;
use Gates\Domain\TemplateScope;
use Gates\Domain\TemplateViolation;
use InvalidArgumentException;
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\Token;

use function max;
use function preg_match;
use function strpos;
use function strtolower;
use function substr;
use function substr_count;

use const T_INLINE_HTML;
use const T_OPEN_TAG;

/**
 * Checks one component template against the plain PHP grammar (#96): its
 * place, its syntax, its typing line, its open tags, then every construct.
 * `violations()` is the seam gate:25 and #97's build call.
 */
final readonly class TemplateValidator
{
    /** Every template sits in the theme (#153), or is one of the framework's atoms. */
    private const string PLACEMENT = 'place a template in the configured theme: themes/<theme>/components/<Name>/<name>.tpl.php'
        . ' or themes/<theme>/layouts/, or a fixed framework atom catalog binding';

    /** A theme's component and layout templates. */
    private const string DECLARED_PLACE = '#^themes/[a-z][a-z0-9-]*/(?:components/[A-Z][A-Za-z0-9]*|layouts)'
        . '/[a-z0-9][a-z0-9-]*\\.tpl\\.php\\z#';

    /**
     * The short open tag refusal's detail.
     */
    private const string SHORT_TAG = 'open PHP with <?php or <?=; write <?xml from a component';

    /**
     * @param TemplateSyntax $templateSyntax The parser.
     * @param TemplateHead   $templateHead   The typing line.
     */
    public function __construct(
        private TemplateSyntax $templateSyntax = new TemplateSyntax(),
        private TemplateHead $templateHead = new TemplateHead(),
    ) {
    }

    /**
     * @param string          $path            The template, relative to the root it was found under.
     * @param string          $source          Its source.
     * @param TemplateBinding $templateBinding The required validated declaration.
     *
     * @throws InvalidArgumentException When the fixed library catalog contains invalid or duplicate declarations.
     *
     * @return list<TemplateViolation> Every construct it refuses; none when the template follows the grammar.
     */
    public function violations(string $path, string $source, TemplateBinding $templateBinding): array
    {
        $placed = $templateBinding->source === $path
            && (preg_match(self::DECLARED_PLACE, $path) === 1 || (new AtomCatalog())->contains($templateBinding));
        $placement = $placed
            ? []
            : [new TemplateViolation($path, 1, TemplateRefusal::Placement, self::PLACEMENT)];

        try {
            [$stmts, $tokens] = $this->templateSyntax->parse($source);
        } catch (Error $error) {
            $line = max(1, $error->getStartLine());

            return [...$placement, new TemplateViolation($path, $line, TemplateRefusal::Syntax, $error->getRawMessage())];
        }

        $component = $this->templateHead->component($path, $source, $stmts, $templateBinding);

        if ($component instanceof TemplateViolation) {
            return [...$placement, $component];
        }

        $templateGrammarVisitor = new TemplateGrammarVisitor($path, $tokens, new TemplateScope($component));
        (new NodeTraverser($templateGrammarVisitor))->traverse($stmts);

        return [...$placement, ...$this->shortTags($path, $tokens), ...$templateGrammarVisitor->violations()];
    }

    /**
     * Short open tags are refused whatever `short_open_tag` says: off, `<?` lexes as markup; on, as an open tag.
     *
     * @param string      $path   The template.
     * @param list<Token> $tokens Its tokens.
     *
     * @return list<TemplateViolation> One per short open tag; a literal `<?xml` in markup counts as one.
     */
    private function shortTags(string $path, array $tokens): array
    {
        $violations = [];

        foreach ($tokens as $token) {
            $line = $this->shortTagLine($token);

            if ($line === null) {
                continue;
            }

            $violations[] = new TemplateViolation($path, $line, TemplateRefusal::ShortOpenTag, self::SHORT_TAG);
        }

        return $violations;
    }

    /**
     * @param Token $token A token.
     *
     * @return int|null The line of the short open tag it holds; null when it holds none.
     */
    private function shortTagLine(Token $token): ?int
    {
        if ($token->id === T_OPEN_TAG) {
            return strtolower(substr($token->text, 0, 5)) === '<?php'
                ? null
                : $token->line;
        }

        $offset = $token->id === T_INLINE_HTML
            ? strpos($token->text, '<?')
            : false;

        return $offset === false ? null : $token->line + substr_count(substr($token->text, 0, $offset), "\n");
    }
}
