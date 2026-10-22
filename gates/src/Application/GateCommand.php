<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\SuiteGate;

use function ctype_digit;
use function fwrite;
use function is_resource;
use function proc_close;
use function proc_open;
use function str_pad;

use const PHP_BINARY;
use const PHP_EOL;
use const STDERR;
use const STDIN;
use const STDOUT;

/**
 * `vendor/bin/aic-gate` (#116): with no argument the whole suite through the
 * runner; `<N>` one gate, its output streamed and its exit code returned;
 * `--numbers` the `gate:N` names the runner reads, one a line; `--list` each
 * with its scope and description; `tools` the external tools' installer; `serve [port]`
 * the compiled site on 127.0.0.1. Everything runs in the project's root.
 */
final readonly class GateCommand
{
    /**
     * How to call it.
     */
    private const string USAGE = 'usage: aic-gate [<N> | --numbers | --list | tools | serve [port]]';

    /**
     * @param GateSuite $gateSuite The suite.
     * @param string    $root      The project's root.
     * @param string    $package   This package's directory.
     */
    public function __construct(
        private GateSuite $gateSuite,
        private string $root,
        private string $package,
    ) {
    }

    /**
     * @param list<string> $arguments The arguments after the command's name.
     *
     * @return int The exit code: the run's, 0 for a listing, 2 for a call it does not know.
     */
    public function run(array $arguments): int
    {
        $first = $arguments[0] ?? '';

        return match (true) {
            $arguments === [] => $this->stream(['bash', $this->package . '/bin/gate-runner.sh']),
            $arguments === ['--numbers'] => $this->print(false),
            $arguments === ['--list'] => $this->print(true),
            $arguments === ['tools'] => $this->stream(['bash', $this->package . '/bin/install-tools.sh']),
            $first === 'serve' => $this->stream([PHP_BINARY, $this->package . '/bin/gate-native-site.php', ...$arguments]),
            ctype_digit($first) && $arguments === [$first] => $this->gate((int) $first),
            default => $this->refuse(self::USAGE),
        };
    }

    /**
     * @param int $number A gate's N.
     *
     * @return int Its exit code; 2 when the suite has no such gate.
     */
    private function gate(int $number): int
    {
        $suiteGate = $this->gateSuite->find($number);

        return $suiteGate instanceof SuiteGate
            ? $this->stream($suiteGate->command)
            : $this->refuse('aic-gate: the suite has no gate:' . $number . '; aic-gate --list names them.');
    }

    /**
     * @param bool $described Whether each name carries its description.
     *
     * @return int 0.
     */
    private function print(bool $described): int
    {
        foreach ($this->gateSuite->gates() as $suiteGate) {
            $line = $described
                ? str_pad($suiteGate->name(), 9) . str_pad('[' . $suiteGate->scope . ']', 6) . $suiteGate->description
                : $suiteGate->name();
            fwrite(STDOUT, $line . PHP_EOL);
        }

        return 0;
    }

    /**
     * @param list<string> $command An argv.
     *
     * @return int Its exit code, its output passed through as it comes; 1 when it cannot start.
     */
    private function stream(array $command): int
    {
        $process = proc_open($command, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, $this->root);

        return is_resource($process) && $pipes === []
            ? proc_close($process)
            : 1;
    }

    /**
     * @param string $message Why.
     *
     * @return int 2.
     */
    private function refuse(string $message): int
    {
        fwrite(STDERR, $message . PHP_EOL);

        return 2;
    }
}
