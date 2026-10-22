<?php

declare(strict_types=1);

namespace Gates\Templates;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Html;
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
 * The escaping rule (#97): what `<?= expr ?>` becomes, by expr's static type.
 * A string is escaped; an int or float is cast, then escaped; an `Html` is
 * written as it is; a `Region` is its element, written bare; a `Keyed`
 * component renders between markers keyed by its own key; any other
 * component between markers keyed by its place. Anything else is refused:
 * the template has nothing it can write safely.
 */
final readonly class TemplateEchoes
{
    /**
     * @param string       $code         The printed expression.
     * @param TemplateType $templateType Its static type.
     * @param string       $key          The compile-time key a component here takes: `Name.N`.
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
                TemplateRefusal::EchoType->value . ': <?= ?> writes a string, number, Html or Component; give the component one as a prop',
            );
        }

        return $this->object($this->wrapped($code), $templateType->class, $key, $inLoop);
    }

    /**
     * @param string $code   The printed object expression, safe to call a method on.
     * @param string $class  Its class.
     * @param string $key    The compile-time key a component here takes.
     * @param bool   $inLoop Whether the echo is inside a loop.
     *
     * @throws InvalidArgumentException When it is no Html, Region or Component, or an unkeyed component in a loop.
     *
     * @return TemplateNode The node that writes it.
     */
    private function object(string $code, string $class, string $key, bool $inLoop): TemplateNode
    {
        return match (true) {
            $class === Html::class => new TemplateNode(TemplateNodeKind::Raw, $code),
            $class === Region::class => new TemplateNode(TemplateNodeKind::Raw, $code . '->render()'),
            is_a($class, Keyed::class, true) => new TemplateNode(TemplateNodeKind::Component, $code, binding: $code . '->key()', key: $key),
            is_a($class, Component::class, true) && $inLoop => throw new InvalidArgumentException(
                TemplateRefusal::ComponentKey->value . ': a component in a loop is Keyed, so Tessera can find each one',
            ),
            is_a($class, Component::class, true) => new TemplateNode(TemplateNodeKind::Component, $code, key: $key),
            default => throw new InvalidArgumentException(
                TemplateRefusal::EchoType->value . ': <?= ?> writes a string, a number, Html or a Component; ' . $class . ' is none',
            ),
        };
    }

    /**
     * @param string $code A printed expression.
     *
     * @return string It, in parentheses unless it is a plain variable or property read.
     */
    private function wrapped(string $code): string
    {
        return preg_match('/^\\$\\w+(->\\w+)*$/', $code) === 1
            ? $code
            : '(' . $code . ')';
    }
}
