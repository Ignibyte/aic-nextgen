<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Guard;

use function in_array;
use function preg_match;
use function str_contains;

/**
 * A simple command while its line is read (#102): its words as written, its
 * write redirects, its heredoc, and what the next word is for. Each token
 * gives a new draft; an operator that ends the command hands the finished
 * one back.
 */
final readonly class Draft
{
    /**
     * Operators that end a simple command.
     */
    private const array ENDS = [';', '&&', '||', '|', '&', "\n", '(', ')', '{', '}', ';;'];

    /**
     * @param list<string> $raws     Its words as written.
     * @param list<string> $writes   Its write redirects' targets, as written.
     * @param string       $heredoc  Its heredoc's body.
     * @param string       $pending  What the next word is: `write`, `read`, `heredoc`, or empty for a word.
     * @param int          $heredocs How many heredocs the line has started so far.
     */
    public function __construct(
        public array $raws = [],
        public array $writes = [],
        public string $heredoc = '',
        public string $pending = '',
        public int $heredocs = 0,
    ) {
    }

    /**
     * @param string       $kind   The token's kind: `op`, `word`, `space` or `stray`.
     * @param string       $text   Its text.
     * @param list<string> $bodies The line's heredoc bodies, in order.
     *
     * @return array{self, list<self>} The draft after it, and the command it finished, if any.
     */
    public function take(string $kind, string $text, array $bodies): array
    {
        if ($kind === 'word') {
            return [$this->word($text, $bodies), []];
        }

        if ($kind !== 'op') {
            return [$this, []];
        }

        return in_array($text, self::ENDS, true)
            ? [new self([], [], '', '', $this->heredocs), [$this]]
            : [$this->redirect($text), []];
    }

    /**
     * @param string $operator A redirect operator.
     *
     * @return self The draft, waiting for the redirect's word.
     */
    private function redirect(string $operator): self
    {
        $pending = match (true) {
            $operator === '<<' || $operator === '<<-' => 'heredoc',
            preg_match('/[<&]\\d*-?$/', $operator) === 1, str_contains($operator, '<') => 'read',
            default => 'write',
        };

        return new self($this->raws, $this->writes, $this->heredoc, $pending, $this->heredocs);
    }

    /**
     * @param string       $raw    A word as written.
     * @param list<string> $bodies The line's heredoc bodies.
     *
     * @return self The draft with it placed.
     */
    private function word(string $raw, array $bodies): self
    {
        return match ($this->pending) {
            'write' => new self($this->raws, [...$this->writes, $raw], $this->heredoc, '', $this->heredocs),
            'read' => new self($this->raws, $this->writes, $this->heredoc, '', $this->heredocs),
            'heredoc' => new self($this->raws, $this->writes, $bodies[$this->heredocs] ?? '', '', $this->heredocs + 1),
            default => new self([...$this->raws, $raw], $this->writes, $this->heredoc, '', $this->heredocs),
        };
    }
}
