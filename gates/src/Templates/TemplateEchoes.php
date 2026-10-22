<?php

declare(strict_types=1);

namespace Gates\Templates;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Keyed;
use AIC\Component\Contracts\Region;
use Gates\Domain\TemplateNode;
use Gates\Domain\TemplateNodeKind;
use Gates\Domain\TemplateRefusal;
use Gates\Domain\TemplateType;
use Gates\Domain\TemplateTypeKind;
use InvalidArgumentException;

use function is_a;
use function preg_match;

/**
 * The escaping rule (#97, #98): what `<?= expr ?>` becomes, by expr's static
 * type. A string is escaped; an int or float is cast, then escaped; a
 * `Region` is written by the render pass with its markers; a `Keyed`
 * component renders through the pass between markers keyed by its own key,
 * any other component between markers keyed by its place, and a layout's
 * components inline. An `Html` waits for #99's `RawHtml`, and anything else is
 * refused: the template has nothing it can write safely.
 */
final readonly class TemplateEchoes
{
    /**
     * @param string       $code         The printed expression.
     * @param TemplateType $templateType Its static type.
     * @param string       $key          The compile-time key a component here takes, `Name.N`; empty in a layout,
     *                                   whose own components are written inline.
     * @param bool         $inLoop       Whether the echo is inside a loop.
     *
     * @throws InvalidArgumentException When the type is not one `<?= ?>` writes (`echo type`, `component key`).
     *
     * @return TemplateNode The node that writes it.
     */
    public function echo(string $code, TemplateType $templateType, string $key, bool $inLoop): TemplateNode
    {
        if ($templateType->only(TemplateTypeKind::String)) {
            return new TemplateNode(TemplateNodeKind::Escaped, $code);
        }

        if ($templateType->scalar()) {
            return new TemplateNode(TemplateNodeKind::Escaped, '(string) (' . $code . ')');
        }

        if (!$templateType->only(TemplateTypeKind::Object)) {
            throw new InvalidArgumentException(
                TemplateRefusal::EchoType->value . ': <?= ?> writes a string, number, Region or Component; make it a prop of one',
            );
        }

        return $this->object($code, $templateType->class, $key, $inLoop);
    }

    /**
     * @param string $code   The printed expression, an object.
     * @param string $class  Its class.
     * @param string $key    The compile-time key a component here takes; empty for inline.
     * @param bool   $inLoop Whether the echo is inside a loop.
     *
     * @throws InvalidArgumentException When the class is not one `<?= ?>` writes (an Html among them, until #99's
     *                                  RawHtml), or a component in a loop has no key.
     *
     * @return TemplateNode The node that writes it.
     */
    private function object(string $code, string $class, string $key, bool $inLoop): TemplateNode
    {
        if ($class === Region::class) {
            return new TemplateNode(TemplateNodeKind::Raw, '$renderPass->region(' . $code . ')');
        }

        if (!is_a($class, Component::class, true)) {
            throw new InvalidArgumentException(
                TemplateRefusal::EchoType->value . ': <?= ?> writes a string, a number, a Region or a Component; ' . $class . ' is none',
            );
        }

        return $this->component($code, $class, $key, $inLoop);
    }

    /**
     * @param string $code   The printed expression, a component.
     * @param string $class  Its class.
     * @param string $key    The compile-time key it takes; empty for inline.
     * @param bool   $inLoop Whether the echo is inside a loop.
     *
     * @throws InvalidArgumentException When it is in a loop and has no key of its own.
     *
     * @return TemplateNode The node that writes it through the render pass.
     */
    private function component(string $code, string $class, string $key, bool $inLoop): TemplateNode
    {
        return match (true) {
            $key === '' => new TemplateNode(TemplateNodeKind::Raw, '$renderPass->inline(' . $code . ')'),
            is_a($class, Keyed::class, true) => new TemplateNode(
                TemplateNodeKind::Component,
                $code,
                binding: $this->wrapped($code) . '->key()',
                key: $key,
            ),
            $inLoop => throw new InvalidArgumentException(
                TemplateRefusal::ComponentKey->value . ': a component in a loop is Keyed, so Tessera can find each one',
            ),
            default => new TemplateNode(TemplateNodeKind::Component, $code, key: $key),
        };
    }

    /**
     * @param string $code A printed expression.
     *
     * @return string It, in parentheses unless it is a variable or a chain of property reads.
     */
    private function wrapped(string $code): string
    {
        return preg_match('/^\\$\\w+(->\\w+)*$/', $code) === 1
            ? $code
            : '(' . $code . ')';
    }
}
