<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use function array_intersect_key;
use function array_key_exists;
use function fclose;
use function feof;
use function fread;
use function is_int;
use function max;
use function min;
use function stream_select;
use function stream_set_blocking;

/** Multiplexes command input, output and private session messages under the same clock. */
final class ProcessStreams
{
    private readonly ProcessMessages $processMessages;

    /** @var array<int, ProcessInput> */
    private array $writers = [];

    private string $output = '';

    /**
     * @param array<array-key, resource> $pipes The helper's five pipes.
     * @param string                     $input Command stdin.
     */
    public function __construct(private array $pipes, string $input)
    {
        foreach ($pipes as $pipe) {
            stream_set_blocking($pipe, false);
        }

        $this->processMessages = new ProcessMessages();
        $this->addWriter(0, $input);
    }

    /** Allow the helper to launch only after its group has been claimed. */
    public function acknowledge(): void
    {
        $this->addWriter(4, 'A');
    }

    /** @return string All observed stdout and stderr bytes. */
    public function output(): string
    {
        return $this->output;
    }

    /** @return ProcessMessages The helper's private protocol. */
    public function messages(): ProcessMessages
    {
        return $this->processMessages;
    }

    /**
     * @param bool $includeControl Whether the private protocol also keeps a read pending.
     *
     * @return bool Whether any selected reader remains open.
     */
    public function reading(bool $includeControl = false): bool
    {
        $channels = $includeControl
            ? [1 => true, 2 => true, 3 => true]
            : [1 => true, 2 => true];

        return array_intersect_key($this->pipes, $channels) !== [];
    }

    /** @param float $remaining Seconds left in the current deadline or teardown grace. */
    public function cycle(float $remaining): void
    {
        $read = array_intersect_key($this->pipes, [1 => true, 2 => true, 3 => true]);
        $write = array_intersect_key($this->pipes, $this->writers);
        $except = null;

        if ($read === [] && $write === []) {
            return;
        }

        if (stream_select($read, $write, $except, 0, max(1, (int) (min(0.1, $remaining) * 1_000_000.0))) === false) {
            return;
        }

        $this->readReady($read);
        $this->writeReady($write);
    }

    /** Stop sending command input or ACK during teardown. */
    public function stopWriting(): void
    {
        $this->close(0);
        $this->close(4);
    }

    /** Close all remaining descriptors, including readers held by descendants. */
    public function finish(): void
    {
        foreach ($this->pipes as $pipe) {
            fclose($pipe);
        }

        $this->pipes = [];
        $this->writers = [];
    }

    /** @param array<array-key, resource> $read The selected readable pipes. */
    private function readReady(array $read): void
    {
        foreach ($read as $descriptor => $pipe) {
            if (!is_int($descriptor)) {
                continue;
            }

            $this->read($descriptor, [$pipe]);
        }
    }

    /** @param array<array-key, resource> $write The selected writable pipes. */
    private function writeReady(array $write): void
    {
        foreach ($write as $descriptor => $pipe) {
            if (!is_int($descriptor)) {
                continue;
            }

            $this->write($descriptor, [$pipe]);
        }
    }

    /**
     * @param int    $descriptor The write descriptor.
     * @param string $input      Its pending bytes.
     */
    private function addWriter(int $descriptor, string $input): void
    {
        $processInput = new ProcessInput($input);

        if ($processInput->complete()) {
            $this->close($descriptor);

            return;
        }

        $this->writers[$descriptor] = $processInput;
    }

    /**
     * @param int                $descriptor The readable descriptor.
     * @param array{0: resource} $pipe       Its pipe.
     */
    private function read(int $descriptor, array $pipe): void
    {
        $bytes = (string) fread($pipe[0], 65_536);

        $this->collect($descriptor, $bytes);

        if (!feof($pipe[0])) {
            return;
        }

        $this->close($descriptor);
    }

    /**
     * @param int    $descriptor The read channel.
     * @param string $bytes      Its bytes.
     */
    private function collect(int $descriptor, string $bytes): void
    {
        if ($descriptor === 3) {
            $this->processMessages->append($bytes);

            return;
        }

        $this->output .= $bytes;
    }

    /**
     * @param int                $descriptor The writable descriptor.
     * @param array{0: resource} $pipe       Its pipe.
     */
    private function write(int $descriptor, array $pipe): void
    {
        if (!array_key_exists($descriptor, $this->writers) || !$this->writers[$descriptor]->write($pipe)) {
            return;
        }

        $this->close($descriptor);
    }

    /** @param int $descriptor The pipe to close once. */
    private function close(int $descriptor): void
    {
        if (!array_key_exists($descriptor, $this->pipes)) {
            return;
        }

        fclose($this->pipes[$descriptor]);
        unset($this->pipes[$descriptor], $this->writers[$descriptor]);
    }
}
