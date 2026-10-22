<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Guard;

use function array_shift;
use function explode;
use function implode;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function trim;

use const PREG_SET_ORDER;

/**
 * Cuts a shell line into tokens (#102, a trimmed form of Droost's
 * `operator_commands_scan_text`): heredoc bodies come out first, kept in
 * order, then words (quotes, escapes, `$( )` and backticks kept whole) and
 * operators (separators, pipes, groups, redirects).
 */
final readonly class ShellTokens
{
    /**
     * One token: blank space, an operator, a word, or a stray character.
     */
    private const string TOKEN = <<<'REGEX'
        ~(?<space>[ \t]+|\\\n)
        |(?<op>&&|\|\||;;|<<-?|&>>?|\d*>>|\d*>\||\d*>&\d*-?|\d*>|\d*<&\d*-?|\d*<|[;&|()\n{}])
        |(?<word>(?:'[^']*'|"(?:\\.|[^"\\])*"|\$\((?<sub>(?:[^()]|\((?&sub)\))*)\)|`(?:\\.|[^`\\])*`|\\.|[^\s'"`;&|()<>\\])+)
        |(?<stray>.)~xs
        REGEX;

    /**
     * A heredoc's start: `<<` or `<<-`, then its delimiter, quoted or not.
     */
    private const string HEREDOC = '/<<-?\\s*([\'"]?)([A-Za-z_][A-Za-z0-9_]*)\\1/';

    /**
     * @param string $line A shell line, perhaps several lines.
     *
     * @return array{list<array{string, string}>, list<string>} Its tokens as kind and text, and its heredoc bodies in order.
     */
    public function cut(string $line): array
    {
        [$text, $bodies] = $this->heredocs($line);
        preg_match_all(self::TOKEN, $text, $matches, PREG_SET_ORDER);
        $tokens = [];

        foreach ($matches as $match) {
            $tokens[] = $this->token($match);
        }

        return [$tokens, $bodies];
    }

    /**
     * @param string $raw A word as written.
     *
     * @return bool Whether the shell fills part of it in at run time: `$(`, a backtick, `${` or `$NAME`, outside single quotes.
     */
    public function dynamic(string $raw): bool
    {
        $unquoted = (string) preg_replace("/'[^']*'/", '', $raw);

        return preg_match('/\\$[({A-Za-z_]|`/', $unquoted) === 1;
    }

    /**
     * @param array<array-key, string> $match One token's match.
     *
     * @return array{string, string} Its kind and its text.
     */
    private function token(array $match): array
    {
        foreach (['op', 'word', 'stray'] as $kind) {
            if (($match[$kind] ?? '') !== '') {
                return [$kind, $match[$kind]];
            }
        }

        return ['space', $match[0]];
    }

    /**
     * @param string $line A shell line.
     *
     * @return array{string, list<string>} The line without its heredoc bodies, and the bodies in order.
     */
    private function heredocs(string $line): array
    {
        $lines = explode("\n", $line);
        $kept = [];
        $bodies = [];

        while ($lines !== []) {
            $current = array_shift($lines);
            $kept[] = $current;

            foreach ($this->delimiters($current) as $delimiter) {
                [$body, $lines] = $this->body($lines, $delimiter);
                $bodies[] = $body;
            }
        }

        return [implode("\n", $kept), $bodies];
    }

    /**
     * @param string $line One line.
     *
     * @return list<string> The delimiters of the heredocs it starts, in order.
     */
    private function delimiters(string $line): array
    {
        return preg_match_all(self::HEREDOC, $line, $matches) === false
            ? []
            : $matches[2];
    }

    /**
     * @param list<string> $lines     The lines after a heredoc's start.
     * @param string       $delimiter Its delimiter.
     *
     * @return array{string, list<string>} Its body, and the lines after its delimiter line.
     */
    private function body(array $lines, string $delimiter): array
    {
        $body = [];

        while ($lines !== []) {
            $next = array_shift($lines);

            if (trim($next) === $delimiter) {
                break;
            }

            $body[] = $next;
        }

        return [implode("\n", $body), $lines];
    }
}
