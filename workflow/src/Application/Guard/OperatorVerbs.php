<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Guard;

use AIC\Workflow\Domain\Guard\Invocation;

use function array_any;
use function basename;
use function implode;
use function in_array;
use function str_contains;
use function str_starts_with;

/**
 * The operator's verbs, refused in the agent's shell run or no run (#102,
 * Droost's `operator_commands_guard`): `workflow:reset`, `workflow:answer` (#117) and `workflow:amend`
 * (#267) anywhere a command
 * names it; any `workflow:*` under a pseudo-terminal, which would fake the
 * operator's terminal; an `aic` command the shell fills in at run time; and
 * the composer verbs that remove the workflow. A heredoc fed to a program
 * that is no shell is data and may name them. #103 to #105 add their verbs
 * to VERBS.
 */
final readonly class OperatorVerbs
{
    /**
     * The workflow commands only the operator runs.
     */
    public const array VERBS = ['workflow:reset', 'workflow:answer', 'workflow:amend'];

    /**
     * Why each is the operator's, by verb.
     */
    private const array WHY = [
        'workflow:amend' => "workflow:amend is the operator's: only they amend a frozen spec row, from a terminal.",
        'workflow:answer' => "workflow:answer is the operator's: only they answer a run's question, from a terminal.",
        'workflow:reset' => "workflow:reset is the operator's: only they abandon a run, from a terminal.",
    ];

    /**
     * Programs that give what they run a pseudo-terminal.
     */
    private const array PTY = ['script', 'unbuffer', 'expect', 'socat', 'empty', 'pty'];

    /**
     * @param list<Invocation> $invocations A shell line's commands.
     *
     * @return string Why the line is the operator's; empty when it is not.
     */
    public function refusal(array $invocations): string
    {
        foreach ($invocations as $invocation) {
            $why = $this->why($invocation);

            if ($why !== '') {
                return $why;
            }
        }

        return '';
    }

    /**
     * @param Invocation $invocation One command that names an operator's verb.
     *
     * @return string Why the first verb it names is the operator's.
     */
    private function verb(Invocation $invocation): string
    {
        foreach (self::WHY as $verb => $why) {
            if ($this->names($invocation, [$verb])) {
                return $why;
            }
        }

        return self::WHY['workflow:reset'];
    }

    /**
     * @param Invocation $invocation One command.
     *
     * @return string Why it is the operator's; empty when it is not.
     */
    private function why(Invocation $invocation): string
    {
        $workflow = $this->names($invocation, ['workflow:']);

        return match (true) {
            $this->names($invocation, self::VERBS) => $this->verb($invocation),
            $this->pty($invocation) && $workflow => "a workflow command under a pseudo-terminal fakes the operator's terminal.",
            $this->aic($invocation) && $invocation->anyDynamic() => 'an aic command the shell fills in cannot be judged; write it out.',
            ($invocation->dynamic[0] ?? false) && $workflow => 'a workflow command run through a variable cannot be judged; write it out.',
            $this->composer($invocation) => "removing the workflow's packages is the operator's.",
            default => '',
        };
    }

    /**
     * @param Invocation   $invocation A command.
     * @param list<string> $needles    What to look for.
     *
     * @return bool Whether any of its words holds one of them.
     */
    private function names(Invocation $invocation, array $needles): bool
    {
        $words = implode("\0", $invocation->words);
        return array_any($needles, static fn (string $needle): bool => str_contains($words, $needle));
    }

    /**
     * @param Invocation $invocation A command.
     *
     * @return bool Whether it runs what follows under a pseudo-terminal.
     */
    private function pty(Invocation $invocation): bool
    {
        $program = $invocation->program();

        return in_array($program, self::PTY, true) || (str_starts_with($program, 'python') && $this->names($invocation, ['pty']));
    }

    /**
     * @param Invocation $invocation A command.
     *
     * @return bool Whether it runs `aic`, directly, through php, or through eval.
     */
    private function aic(Invocation $invocation): bool
    {
        $program = $invocation->program();
        $first = basename($invocation->arguments()[0] ?? '');

        return $program === 'aic'
            || (str_starts_with($program, 'php') && $first === 'aic')
            || ($program === 'eval' && $this->names($invocation, ['aic']));
    }

    /**
     * @param Invocation $invocation A command.
     *
     * @return bool Whether it is `composer install|update --no-dev` or `composer remove ignibyte/aic-workflow`.
     */
    private function composer(Invocation $invocation): bool
    {
        if ($invocation->program() !== 'composer') {
            return false;
        }

        $verb = $invocation->arguments()[0] ?? '';

        return (in_array($verb, ['install', 'update', 'i', 'u'], true) && in_array('--no-dev', $invocation->words, true))
            || (in_array($verb, ['remove', 'rm'], true) && in_array('ignibyte/aic-workflow', $invocation->words, true));
    }
}
