<?php

declare(strict_types=1);

namespace Gates\Templates;

use Gates\Domain\TemplateBinding;
use Gates\Domain\TemplateReader;
use Gates\Domain\TemplateRefusal;
use Gates\Domain\TemplateType;
use Gates\Domain\TemplateTypeKind;
use Gates\Domain\TemplateUnit;
use Gates\Domain\TemplateViolation;
use InvalidArgumentException;
use Override;
use PhpParser\Error;
use PhpParser\Node\Stmt;

use function array_map;
use function implode;
use function lcfirst;
use function max;
use function preg_match;
use function strrpos;
use function substr;

/**
 * Reads a plain PHP template for the build (#97). It is validated first,
 * with gate:25's own validator, so a template the gate refuses never
 * compiles; then its typing line names the component, and its statements are
 * lowered to nodes. The binding fixes its generated class and wrapper role.
 */
final readonly class PlainTemplateReader implements TemplateReader
{
    /**
     * @param TemplateValidator  $templateValidator  gate:25's checks.
     * @param TemplateSyntax     $templateSyntax     The parser.
     * @param TemplateHead       $templateHead       The typing line.
     * @param TemplateClassTypes $templateClassTypes The component's checks.
     */
    public function __construct(
        private TemplateValidator $templateValidator = new TemplateValidator(),
        private TemplateSyntax $templateSyntax = new TemplateSyntax(),
        private TemplateHead $templateHead = new TemplateHead(),
        private TemplateClassTypes $templateClassTypes = new TemplateClassTypes(),
    ) {
    }

    /**
     * @param string          $path            The template, relative to the repo root.
     * @param string          $source          Its source.
     * @param TemplateBinding $templateBinding Its required input and output declaration.
     *
     * @throws InvalidArgumentException When it breaks the grammar or a type rule: one `path:line: category: detail` line each.
     *
     * @return TemplateUnit The template read.
     */
    #[Override]
    public function read(string $path, string $source, TemplateBinding $templateBinding): TemplateUnit
    {
        $this->validate($path, $source, $templateBinding);
        $stmts = $this->statements($path, $source);
        $this->templateHead->component($path, $source, $stmts, $templateBinding);
        $component = $templateBinding->component;
        $short = substr($component, (int) strrpos($component, '\\') + 1);

        try {
            $this->templateClassTypes->component($component, $path);
            $scope = [lcfirst($short) => new TemplateType([TemplateTypeKind::Object], $component)];
            $nodes = (new TemplateLowering($short, $templateBinding->transparent()))->lower($stmts, $scope);
        } catch (InvalidArgumentException $invalidArgumentException) {
            $message = $path . ':' . $this->located($invalidArgumentException->getMessage());

            throw new InvalidArgumentException($message, 0, $invalidArgumentException);
        }

        return new TemplateUnit($templateBinding, $nodes);
    }

    /**
     * @param string          $path            The authored template path.
     * @param string          $source          Its source bytes.
     * @param TemplateBinding $templateBinding Its required input identity.
     *
     * @throws InvalidArgumentException When any restricted grammar construct is refused.
     */
    private function validate(string $path, string $source, TemplateBinding $templateBinding): void
    {
        $violations = $this->templateValidator->violations($path, $source, $templateBinding);

        if ($violations !== []) {
            throw new InvalidArgumentException(implode("\n", array_map(
                static fn (TemplateViolation $templateViolation): string => $templateViolation->message(),
                $violations,
            )));
        }
    }

    /**
     * @param string $path   The template's relative path.
     * @param string $source Its previously validated PHP source.
     *
     * @throws InvalidArgumentException When a parser failure prevents reading its statements.
     *
     * @return list<Stmt> Its statements, or a located syntax refusal.
     */
    private function statements(string $path, string $source): array
    {
        try {
            return $this->templateSyntax->parse($source)[0];
        } catch (Error $error) {
            $templateViolation = new TemplateViolation(
                $path,
                max(1, $error->getStartLine()),
                TemplateRefusal::Syntax,
                $error->getRawMessage(),
            );

            throw new InvalidArgumentException($templateViolation->message(), 0, $error);
        }
    }

    /**
     * @param string $message A refusal: `line: category: detail`, or `category: detail` for the typing line.
     *
     * @return string It with a line: the typing line's is 1.
     */
    private function located(string $message): string
    {
        return preg_match('/^\\d+: /', $message) === 1
            ? $message
            : '1: ' . $message;
    }
}
