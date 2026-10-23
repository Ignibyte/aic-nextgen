<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Domain\ProcessResult;

use function hrtime;
use function proc_close;

/** Owns one unreaped session leader until completion or the last teardown signal. */
final class ProcessSession
{
    private bool $acknowledged = false;

    /**
     * @param array{0: resource} $process        The helper, never polled after ownership is claimed.
     * @param ProcessGroup       $processGroup   Its verified signal boundary.
     * @param ProcessStreams     $processStreams The concurrent input/output channels.
     */
    public function __construct(
        private readonly array $process,
        private readonly ProcessGroup $processGroup,
        private readonly ProcessStreams $processStreams,
    ) {
    }

    /**
     * @param float $deadline The monotonic command deadline in seconds.
     *
     * @return ProcessResult Actual exit and output, or exit 124 after bounded teardown.
     */
    public function run(float $deadline): ProcessResult
    {
        $exit = null;

        try {
            $exit = $this->wait($deadline);

            if ($exit === null) {
                $this->stop();
            }

            return new ProcessResult($exit ?? 124, $this->processStreams->output());
        } finally {
            if ($exit === null) {
                $this->processGroup->force();
            }

            $this->processStreams->finish();
            proc_close($this->process[0]);
        }
    }

    /**
     * @param float $deadline The command deadline.
     *
     * @return int|null The completed command exit, or null on expiry.
     */
    private function wait(float $deadline): ?int
    {
        while ($this->remaining($deadline) > 0) {
            if (!$this->acknowledge()) {
                $this->stop();

                return 127;
            }

            $result = $this->completed();

            if ($result !== null) {
                return $result;
            }

            $this->processStreams->cycle($this->remaining($deadline));
        }

        return null;
    }

    /** @return bool Whether startup remains valid; ownership is established before ACK. */
    private function acknowledge(): bool
    {
        if ($this->acknowledged) {
            return true;
        }

        if (!$this->processStreams->messages()->ready()) {
            return $this->processStreams->reading(true);
        }

        if (!$this->processGroup->claim()) {
            return false;
        }

        $this->acknowledged = true;
        $this->processStreams->acknowledge();

        return true;
    }

    /** @return int|null The result, after every output reader reached EOF. */
    private function completed(): ?int
    {
        return $this->processStreams->reading()
            ? null
            : $this->processStreams->messages()->result();
    }

    /** TERM, fixed grace, KILL and bounded final output drain before reaping. */
    private function stop(): void
    {
        $this->processStreams->stopWriting();
        $this->processGroup->terminate();
        $this->drain((float) hrtime(true) / 1_000_000_000.0 + 0.25);
        $this->processGroup->force();
        $this->drain((float) hrtime(true) / 1_000_000_000.0 + 0.25);
    }

    /** @param float $deadline The current teardown deadline. */
    private function drain(float $deadline): void
    {
        while ($this->remaining($deadline) > 0 && $this->processStreams->reading(true)) {
            $this->processStreams->cycle($this->remaining($deadline));
        }
    }

    /**
     * @param float $deadline A monotonic deadline.
     *
     * @return float Its remaining seconds.
     */
    private function remaining(float $deadline): float
    {
        return $deadline - (float) hrtime(true) / 1_000_000_000.0;
    }
}
