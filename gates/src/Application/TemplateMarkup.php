<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\RegexCaptures;
use Gates\Domain\TemplateMarkupKind;
use InvalidArgumentException;

use function array_key_exists;
use function in_array;
use function preg_match;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;

/** Scan authored literal markup without treating escaped output as HTML delimiters. */
final readonly class TemplateMarkup
{
    use RegexCaptures;

    /**
     * @param string                            $text    Literal authored markup.
     * @param array{TemplateMarkupKind, string} $context The lexical state and current tag or comment tail.
     *
     * @throws InvalidArgumentException When PHP divides an HTML tag name.
     *
     * @return array{TemplateMarkupKind, string} Its ending lexical context.
     */
    public function read(string $text, array $context): array
    {
        for ($offset = 0; $offset < strlen($text); $offset += 1) {
            [$offset, $context] = $this->step($text, $offset, $context);
        }

        return $context;
    }

    /**
     * @param array{TemplateMarkupKind, string} $context One branch's ending context.
     *
     * @return array{TemplateMarkupKind, string} Equivalent completed literal tag names have the same join state.
     */
    public function join(array $context): array
    {
        return $context[0] === TemplateMarkupKind::TagName
            ? [TemplateMarkupKind::Tag, $context[1]]
            : $context;
    }

    /**
     * @param array{TemplateMarkupKind, string} $left  One compatible path's ending state.
     * @param array{TemplateMarkupKind, string} $right The other compatible path's ending state.
     *
     * @return array{TemplateMarkupKind, string} The pending literal name survives if either path can leave it unfinished.
     */
    public function merge(array $left, array $right): array
    {
        return $left[0] === TemplateMarkupKind::TagName
            ? $left
            : $right;
    }

    /**
     * @param string                            $text    Literal markup.
     * @param int                               $offset  The next character.
     * @param array{TemplateMarkupKind, string} $context The current context.
     *
     * @throws InvalidArgumentException When PHP divides an HTML tag name.
     *
     * @return array{int, array{TemplateMarkupKind, string}} Last consumed offset and next context.
     */
    private function step(string $text, int $offset, array $context): array
    {
        return match ($context[0]) {
            TemplateMarkupKind::Body => $this->opening($text, $offset),
            TemplateMarkupKind::OpenTag => throw new InvalidArgumentException(
                'an HTML tag name must be one literal, outside PHP control flow',
            ),
            TemplateMarkupKind::Tag, TemplateMarkupKind::TagName => $this->tag($text, $offset, $context),
            TemplateMarkupKind::SingleQuoted, TemplateMarkupKind::DoubleQuoted => [$offset, $this->quoted($text[$offset], $context)],
            TemplateMarkupKind::Comment => [$offset, $this->comment($text[$offset], $context[1])],
            TemplateMarkupKind::Script, TemplateMarkupKind::Style,
            TemplateMarkupKind::Textarea, TemplateMarkupKind::Title => $this->raw($text, $offset, $context),
        };
    }

    /**
     * @param string $text   Literal markup.
     * @param int    $offset A body character.
     *
     * @return array{int, array{TemplateMarkupKind, string}} The opening token's end and lexical state.
     */
    private function opening(string $text, int $offset): array
    {
        $tail = substr($text, $offset);

        if (in_array($tail, ['<', '</', '<!', '<!-'], true)) {
            return [$offset + strlen($tail) - 1, [TemplateMarkupKind::OpenTag, $tail]];
        }

        if (str_starts_with($tail, '<!--')) {
            return [$offset + 3, [TemplateMarkupKind::Comment, '']];
        }

        $matches = self::regexMatch('/\\A<(\\/?[a-z][a-z0-9:-]*)/i', $tail);

        if (array_key_exists(0, $matches) && array_key_exists(1, $matches)) {
            return $this->startTag($text, $offset, $matches[0], $matches[1]);
        }

        return str_starts_with($tail, '<!')
            ? [$offset + 1, [TemplateMarkupKind::Tag, '!']]
            : [$offset, [TemplateMarkupKind::Body, '']];
    }

    /**
     * @param string $text   Literal markup containing a checked opening token.
     * @param int    $offset The token's first position.
     * @param string $token  The checked complete regex match.
     * @param string $tag    The checked literal tag-name capture.
     *
     * @return array{int, array{TemplateMarkupKind, string}} Its ending offset and lexical state.
     */
    private function startTag(string $text, int $offset, string $token, string $tag): array
    {
        $end = $offset + strlen($token) - 1;
        $kind = $end === strlen($text) - 1
            ? TemplateMarkupKind::TagName
            : TemplateMarkupKind::Tag;

        return [$end, [$kind, strtolower($tag)]];
    }

    /**
     * @param string                            $text    Literal markup.
     * @param int                               $offset  The next tag character.
     * @param array{TemplateMarkupKind, string} $context The tag context and literal tag name.
     *
     * @throws InvalidArgumentException When an authored expression divides a tag name.
     *
     * @return array{int, array{TemplateMarkupKind, string}} Its next context.
     */
    private function tag(string $text, int $offset, array $context): array
    {
        $character = $text[$offset];

        if ($context[0] === TemplateMarkupKind::TagName && preg_match('/[a-z0-9:-]/i', $character) === 1) {
            throw new InvalidArgumentException('an HTML tag name must be one literal, outside PHP control flow');
        }

        $next = match ($character) {
            '"' => [TemplateMarkupKind::DoubleQuoted, $context[1]],
            "'" => [TemplateMarkupKind::SingleQuoted, $context[1]],
            '>' => [$this->content($context[1]), ''],
            default => [TemplateMarkupKind::Tag, $context[1]],
        };

        return [$offset, $next];
    }

    /**
     * @param string $tag A complete literal opening tag name, or a closing name prefixed with slash.
     *
     * @return TemplateMarkupKind Its content context; end tags always return to body.
     */
    private function content(string $tag): TemplateMarkupKind
    {
        return match ($tag) {
            'script' => TemplateMarkupKind::Script,
            'style' => TemplateMarkupKind::Style,
            'textarea' => TemplateMarkupKind::Textarea,
            'title' => TemplateMarkupKind::Title,
            default => TemplateMarkupKind::Body,
        };
    }

    /**
     * @param string                            $character One attribute character.
     * @param array{TemplateMarkupKind, string} $context   Its quoted context.
     *
     * @return array{TemplateMarkupKind, string} A matching quote returns to tag context.
     */
    private function quoted(string $character, array $context): array
    {
        $quote = $context[0] === TemplateMarkupKind::SingleQuoted
            ? "'"
            : '"';

        return [$character === $quote ? TemplateMarkupKind::Tag : $context[0], $context[1]];
    }

    /**
     * @param string $character A comment character.
     * @param string $tail      At most two preceding comment characters.
     *
     * @return array{TemplateMarkupKind, string} The comment tail, or body after its actual closer.
     */
    private function comment(string $character, string $tail): array
    {
        return $tail . $character === '-->'
            ? [TemplateMarkupKind::Body, '']
            : [TemplateMarkupKind::Comment, $character === '-' ? substr($tail . $character, -2) : ''];
    }

    /**
     * @param string                            $text    Literal raw text or escapable raw text.
     * @param int                               $offset  Its next character.
     * @param array{TemplateMarkupKind, string} $context Its raw-text element context.
     *
     * @return array{int, array{TemplateMarkupKind, string}} Only the matching end tag ends raw text.
     */
    private function raw(string $text, int $offset, array $context): array
    {
        $name = strtolower($context[0]->name);
        $pattern = '/\\A<\\/' . $name . '(?=[\\s\\/>]|\\z)/i';

        if ($text[$offset] !== '<') {
            return [$offset, $context];
        }

        $matches = self::regexMatch($pattern, substr($text, $offset));

        if (array_key_exists(0, $matches)) {
            return $this->startTag($text, $offset, $matches[0], '/' . $name);
        }

        $tail = strtolower(substr($text, $offset));

        return str_starts_with('</' . $name, $tail)
            ? [$offset + strlen($tail) - 1, [TemplateMarkupKind::OpenTag, $tail]]
            : [$offset, $context];
    }
}
