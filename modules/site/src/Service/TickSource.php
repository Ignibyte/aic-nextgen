<?php

declare(strict_types=1);

namespace App\Site\Service;

use AIC\Controller\Contracts\EventSource;
use AIC\Controller\Contracts\ServerEvent;
use AIC\Tessera\Domain\TesseraEvents;
use App\Theme\Components\Tick\Tick;
use InvalidArgumentException;
use Override;

use function usleep;

/**
 * Ticks as Tessera events (#73): each appends a Tick component to the
 * `ticks` region under the id N (#115), so a reconnect resumes after the last
 * tick the page saw, and then sets the document's `ticks` signal to N.
 */
final class TickSource implements EventSource
{
    /**
     * Whether a tick has been given yet, so the first comes without a pause.
     */
    private bool $started = false;

    /**
     * Whether the last tick's signal patch is still to come.
     */
    private bool $signalDue = false;

    /**
     * @param int           $count         The last tick, 1 or more.
     * @param int           $given         The last tick given: at first the one the client last saw (`Last-Event-ID`), 0 for none.
     * @param TesseraEvents $tesseraEvents Makes the events.
     */
    public function __construct(
        private readonly int $count,
        private int $given,
        private readonly TesseraEvents $tesseraEvents = new TesseraEvents(),
    ) {
    }

    /**
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return ServerEvent The next tick, after a pause unless it is the first, then its signal patch; a closing
     *                     comment when none is left.
     */
    #[Override]
    public function next(): ServerEvent
    {
        if ($this->signalDue) {
            $this->signalDue = false;

            return $this->tesseraEvents->patchSignals('page', '{"ticks":' . $this->given . '}', false, '', $this->given >= $this->count);
        }

        if ($this->given >= $this->count) {
            return $this->tesseraEvents->keepAlive(true);
        }

        if ($this->started) {
            usleep(200_000);
        }

        $this->started = true;
        $this->given += 1;
        $this->signalDue = true;

        return $this->tesseraEvents->patchElements('ticks', new Tick($this->given), 'append', (string) $this->given, false);
    }
}
