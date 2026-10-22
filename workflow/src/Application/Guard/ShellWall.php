<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Guard;

use AIC\Workflow\Domain\Guard\GuardScene;
use AIC\Workflow\Domain\Guard\GuardVerdict;
use AIC\Workflow\Domain\Guard\HookCall;
use AIC\Workflow\Domain\Guard\Invocation;

use function in_array;

/**
 * The wall on Bash (#102, Droost's `operator-commands` verb, order kept):
 * the operator's verbs first, then the protected paths a command writes or
 * names, then each write target through the phase's scope. A `cd` moves
 * where the next command's paths resolve.
 */
final readonly class ShellWall
{
    /**
     * Programs that run the code they are handed, so a heredoc fed to them is code, not data.
     */
    private const array INTERPRETERS = ['php', 'python', 'python3', 'node', 'perl', 'ruby', 'sqlite3', 'bash', 'sh', 'zsh'];

    /**
     * @param ShellWords     $shellWords     Reads the line.
     * @param OperatorVerbs  $operatorVerbs  The operator's verbs.
     * @param ShellWrites    $shellWrites    What each command writes and names.
     * @param ProtectedPaths $protectedPaths What is never written.
     * @param WriteScope     $writeScope     Where the phase writes.
     * @param PathResolver   $pathResolver   Where a path lands.
     */
    public function __construct(
        private ShellWords $shellWords,
        private OperatorVerbs $operatorVerbs,
        private ShellWrites $shellWrites,
        private ProtectedPaths $protectedPaths,
        private WriteScope $writeScope,
        private PathResolver $pathResolver,
    ) {
    }

    /**
     * @param HookCall   $hookCall   A Bash call.
     * @param GuardScene $guardScene The run and the levers.
     *
     * @return GuardVerdict Refused at the first wall a command hits; nudged or allowed otherwise.
     */
    public function check(HookCall $hookCall, GuardScene $guardScene): GuardVerdict
    {
        $invocations = $this->shellWords->parse($hookCall->command);
        $why = $this->operatorVerbs->refusal($invocations);

        if ($why !== '') {
            return new GuardVerdict('refuse', 'operator-verb', $why);
        }

        $cwd = $hookCall->cwd;
        $verdict = new GuardVerdict();

        foreach ($invocations as $invocation) {
            $verdict = $this->strongest($verdict, $this->invocation($invocation, $cwd, $guardScene));
            $cwd = $this->cwdAfter($invocation, $cwd);
        }

        return $verdict;
    }

    /**
     * @param Invocation $invocation A command.
     * @param string     $cwd        Where its paths resolve.
     * @param GuardScene $guardScene The run and the levers.
     *
     * @return GuardVerdict Its verdict.
     */
    private function invocation(Invocation $invocation, string $cwd, GuardScene $guardScene): GuardVerdict
    {
        $why = $this->protectedWhy($invocation, $cwd, $this->protectedPaths->spec($guardScene));

        if ($why !== '') {
            return new GuardVerdict('refuse', 'protected', $why);
        }

        $verdict = new GuardVerdict();

        foreach ($this->shellWrites->targets($invocation) as $target) {
            foreach ($this->pathResolver->expand($target, $cwd) as $absolute) {
                $verdict = $this->strongest($verdict, $this->writeScope->verdict($absolute, $guardScene));
            }
        }

        return $verdict;
    }

    /**
     * @param Invocation $invocation A command.
     * @param string     $cwd        Where its paths resolve.
     * @param string     $spec       The open run's spec, absolute.
     *
     * @return string Why it reaches a protected path; empty when it does not.
     */
    private function protectedWhy(Invocation $invocation, string $cwd, string $spec): string
    {
        if ($this->shellWrites->cleansIgnored($invocation)) {
            return "git clean -x removes what git ignores, the run's state among it.";
        }

        $why = $this->landing($this->shellWrites->targets($invocation), $cwd, $spec, false);

        if ($why !== '' || $this->shellWrites->reads($invocation)) {
            return $why;
        }

        $why = $this->landing($this->shellWrites->paths($invocation), $cwd, $spec, $this->shellWrites->destroys($invocation));

        return $why !== ''
            ? $why
            : $this->mention($invocation);
    }

    /**
     * @param list<string> $words    Paths as written.
     * @param string       $cwd      Where they resolve.
     * @param string       $spec     The open run's spec, absolute.
     * @param bool         $destroys Whether the command reaches into the directories it names.
     *
     * @return string Why one of them lands on a protected path; empty when none does.
     */
    private function landing(array $words, string $cwd, string $spec, bool $destroys): string
    {
        foreach ($words as $word) {
            $why = $this->wordLands($word, $cwd, $spec, $destroys);

            if ($why !== '') {
                return $why;
            }
        }

        return '';
    }

    /**
     * @param string $word     A path as written, perhaps a glob.
     * @param string $cwd      Where it resolves.
     * @param string $spec     The open run's spec, absolute.
     * @param bool   $destroys Whether the command reaches into the directories it names.
     *
     * @return string Why one of its matches lands on a protected path; empty when none does.
     */
    private function wordLands(string $word, string $cwd, string $spec, bool $destroys): string
    {
        foreach ($this->pathResolver->expand($word, $cwd) as $absolute) {
            $why = $this->lands($word, $absolute, $spec, $destroys);

            if ($why !== '') {
                return $why;
            }
        }

        return '';
    }

    /**
     * @param string $word     A path as written.
     * @param string $absolute One place it resolves to.
     * @param string $spec     The open run's spec, absolute.
     * @param bool   $destroys Whether the command reaches into the directories it names.
     *
     * @return string Why it lands on a protected path, or holds one the command would reach; empty otherwise.
     */
    private function lands(string $word, string $absolute, string $spec, bool $destroys): string
    {
        $why = $this->protectedPaths->reason($absolute, $spec);

        return $why === '' && $destroys && $this->protectedPaths->holds($absolute)
            ? $word . " holds the run's state or wiring; it is not removed, moved or re-owned whole."
            : $why;
    }

    /**
     * @param Invocation $invocation A command that does not only read.
     *
     * @return string Why its words, or the code a heredoc hands an interpreter, name a protected path; empty otherwise.
     */
    private function mention(Invocation $invocation): string
    {
        $code = in_array($invocation->program(), self::INTERPRETERS, true)
            ? $invocation->heredoc
            : '';

        foreach ([...$invocation->arguments(), $code] as $text) {
            $why = $this->protectedPaths->mentioned($text);

            if ($why !== '') {
                return $why;
            }
        }

        return '';
    }

    /**
     * @param Invocation $invocation A command.
     * @param string     $cwd        Where it ran.
     *
     * @return string Where the next command runs: moved by a `cd`.
     */
    private function cwdAfter(Invocation $invocation, string $cwd): string
    {
        $target = $invocation->arguments()[0] ?? '';

        return $invocation->program() === 'cd' && $target !== '' && $target !== '-'
            ? $this->pathResolver->absolute($target, $cwd)
            : $cwd;
    }

    /**
     * @param GuardVerdict $kept  The verdict so far.
     * @param GuardVerdict $fresh A command's verdict.
     *
     * @return GuardVerdict The stronger: a refusal over a nudge over an allow; the first of equals.
     */
    private function strongest(GuardVerdict $kept, GuardVerdict $fresh): GuardVerdict
    {
        $rank = ['allow' => 0, 'nudge' => 1, 'refuse' => 2];

        return $rank[$fresh->verdict] > $rank[$kept->verdict]
            ? $fresh
            : $kept;
    }
}
