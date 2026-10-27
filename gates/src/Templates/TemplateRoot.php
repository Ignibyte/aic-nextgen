<?php

declare(strict_types=1);

namespace Gates\Templates;

use Gates\Domain\TemplateBinding;
use Gates\Domain\TemplateRefusal;
use Gates\Domain\TemplateRole;
use Gates\Domain\TemplateViolation;
use PhpParser\Lexer;
use PhpParser\Token;

use function in_array;
use function mb_trim;
use function preg_match;
use function str_ends_with;
use function strlen;
use function strtolower;
use function substr;

use const T_CLOSE_TAG;
use const T_ELSE;
use const T_ELSEIF;
use const T_FOR;
use const T_FOREACH;
use const T_IF;
use const T_INLINE_HTML;
use const T_OPEN_TAG;
use const T_OPEN_TAG_WITH_ECHO;
use const T_SWITCH;

/**
 * gate:25's root rule (#189): a component or atom template renders exactly one
 * element at its top level, outside every condition and loop, with no text
 * beside it, so the build has one root to write Tessera's `data-t-c` on. A
 * component that needs siblings wraps them (`<tbody>` for rows, a `<div>`
 * elsewhere). Layouts are not components; the framework's tracked assets, head
 * links rendered only inline and never marked, are the one template it exempts.
 */
final readonly class TemplateRoot
{
    /** Stands for an echo: text, as far as the top level goes. */
    private const string TEXT = "\0";

    /** Stands for a PHP block holding a condition or a loop. */
    private const string CONTROL = "\1";

    /** The keywords that open or continue a condition or a loop. */
    private const array CONTROLS = [T_IF, T_ELSEIF, T_ELSE, T_FOREACH, T_FOR, T_SWITCH];

    /** Elements without an end tag. */
    private const array VOID = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr'];

    /** At the markup's offset: a comment, an end tag, or a start tag with its name and whether it closes itself. */
    private const string TAG = '#\\G(?:(<!--.*?-->)|</([A-Za-z][A-Za-z0-9-]*)\\s*>'
        . '|<([A-Za-z][A-Za-z0-9-]*)(?:[^>"\']|"[^"]*"|\'[^\']*\')*?(/?)>)#s';

    /**
     * @param Lexer $lexer Splits the source into inline HTML and PHP.
     */
    public function __construct(
        private Lexer $lexer = new Lexer(),
    ) {
    }

    /**
     * @param string          $path            The template, relative to the root.
     * @param string          $source          Its source.
     * @param TemplateBinding $templateBinding Its declaration.
     *
     * @return list<TemplateViolation> One `root` violation when the template has no single root element.
     */
    public function violations(string $path, string $source, TemplateBinding $templateBinding): array
    {
        if ($templateBinding->transparent() || self::inlineOnly($templateBinding)) {
            return [];
        }

        $problem = self::problem($this->shape($source));

        return $problem === ''
            ? []
            : [new TemplateViolation($path, 1, TemplateRefusal::Root, $problem)];
    }

    /**
     * @param string $source A template.
     *
     * @return string Its markup: inline HTML as written, TEXT per echo, CONTROL per block with a condition or loop.
     */
    private function shape(string $source): string
    {
        $shape = '';
        $block = null;

        foreach ($this->lexer->tokenize($source) as $token) {
            [$block, $piece] = self::step($block, $token);
            $shape .= $piece;
        }

        return $shape;
    }

    /**
     * @param TemplateBinding $templateBinding A declaration.
     *
     * @return bool Whether it is the framework's tracked-assets atom.
     */
    private static function inlineOnly(TemplateBinding $templateBinding): bool
    {
        return $templateBinding->role === TemplateRole::Atom && str_ends_with($templateBinding->source, '/tracked-assets.phtml');
    }

    /**
     * @param string|null $block The PHP block open: TEXT for an echo, CONTROL or '' for a statement; null outside.
     * @param Token       $token The next token.
     *
     * @return array{string|null, string} The block open after it, and what it adds to the shape.
     */
    private static function step(?string $block, Token $token): array
    {
        if ($block === null) {
            return match ($token->id) {
                T_INLINE_HTML => [null, $token->text],
                T_OPEN_TAG_WITH_ECHO => [self::TEXT, ''],
                T_OPEN_TAG => ['', ''],
                default => [null, ''],
            };
        }

        if ($token->id === T_CLOSE_TAG) {
            return [null, $block];
        }

        return [in_array($token->id, self::CONTROLS, true) ? self::CONTROL : $block, ''];
    }

    /**
     * @param string $shape A template's shape.
     *
     * @return string What keeps it from having one root element; empty when it has one.
     */
    private static function problem(string $shape): string
    {
        [$offset, $depth, $roots, $stray] = [0, 0, 0, ''];

        while ($offset < strlen($shape) && $stray === '') {
            [$offset, $depth, $roots, $stray] = self::advance($shape, $offset, $depth, $roots);
        }

        return $stray === ''
            ? self::count($roots)
            : $stray;
    }

    /**
     * @param string $shape  A template's shape.
     * @param int    $offset Where the walk is.
     * @param int    $depth  The element depth there.
     * @param int    $roots  The top-level elements so far.
     *
     * @return array{int, int, int, string} The offset after the next comment, tag or character, the depth and the
     *                                      roots after it, and why a stray top-level character may not stand there.
     */
    private static function advance(string $shape, int $offset, int $depth, int $roots): array
    {
        $match = self::matched(self::TAG, $shape, $offset);
        $whole = $match[0] ?? '';

        if ($whole !== '') {
            return [$offset + strlen($whole), ...self::tag($match, $depth, $roots), ''];
        }

        $stray = $depth === 0
            ? self::stray(substr($shape, $offset, 1))
            : '';

        return [$offset + 1, $depth, $roots, $stray];
    }

    /**
     * @param string $character One character of the shape at the top level, outside every tag.
     *
     * @return string Why it may not stand there; empty for whitespace.
     */
    private static function stray(string $character): string
    {
        if (mb_trim($character) === '' || $character === '<') {
            return '';
        }

        return $character === self::CONTROL
            ? 'a condition or a loop around the root; keep one element outside them and put them inside it'
            : 'text beside the root element; put it inside the root';
    }

    /**
     * @param int $roots The top-level elements.
     *
     * @return string Why that many is wrong; empty for one.
     */
    private static function count(int $roots): string
    {
        return match (true) {
            $roots === 0 => 'no root element; a component renders one element, Tessera marks it',
            $roots > 1 => $roots . ' elements at the top level; wrap them in one (a <div>, or a <tbody> for rows)',
            default => '',
        };
    }

    /**
     * @param array<array-key, string> $match A comment or tag the TAG pattern matched.
     * @param int                      $depth The element depth before it.
     * @param int                      $roots The top-level elements so far.
     *
     * @return array{int, int} The depth after it, and the top-level elements counted.
     */
    private static function tag(array $match, int $depth, int $roots): array
    {
        if (($match[1] ?? '') !== '') {
            return [$depth, $roots];
        }

        if (($match[2] ?? '') !== '') {
            return [$depth - 1, $roots];
        }

        $name = strtolower($match[3] ?? '');
        $roots += $depth === 0
            ? 1
            : 0;
        $opens = !in_array($name, self::VOID, true) && ($match[4] ?? '') === '';

        return [$opens ? $depth + 1 : $depth, $roots];
    }

    /**
     * @param non-empty-string $pattern A pattern anchored at the offset.
     * @param string           $subject The text.
     * @param int              $offset  Where it must match.
     *
     * @return array<array-key, string> Its groups; empty when it does not match there.
     */
    private static function matched(string $pattern, string $subject, int $offset): array
    {
        return preg_match($pattern, $subject, $match, 0, $offset) === 1
            ? $match
            : [];
    }
}
