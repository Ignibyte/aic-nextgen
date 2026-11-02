<?php

declare(strict_types=1);

namespace AIC\Profiler\Application;

use AIC\Component\Domain\Badge;
use AIC\Component\Domain\Link;
use AIC\Controller\Contracts\EventSource;
use AIC\Controller\Contracts\ServerEvent;
use AIC\Database\Contracts\QueryException;
use AIC\Profiler\Domain\ProfileRow;
use AIC\Profiler\Domain\ProfilerToolbar;
use AIC\Tessera\Domain\TesseraEvents;
use InvalidArgumentException;
use Override;

use function array_shift;
use function sprintf;
use function usleep;

/**
 * The toolbar's stream (#231). On a fresh connection it starts at the page's
 * own profile, whose figures fill the toolbar's summary (`@profiler-summary`).
 * Each profile the same browser keeps after it becomes a link appended to the
 * list (`@profiler-exchanges`). Each event's id is the profile's, so Tessera
 * resumes after the last one it saw. The stream reads the table once a second
 * and sends a keep-alive when nothing is new. After half a minute it asks
 * Tessera to reconnect, so no worker is held for long.
 */
final class ProfilerStream implements EventSource
{
    /** How many reads before the stream asks Tessera to reconnect. */
    private const int READS = 30;

    /** @var list<ProfileRow> */
    private array $pending = [];

    private int $reads = 0;

    /**
     * @param ProfileReader $profileReader Reads the kept profiles.
     * @param string        $browser       The browser's key.
     * @param string        $from          The page's profile id, or the last one Tessera saw when it resumes.
     * @param bool          $resumed       Whether Tessera resumes, so `from` was sent already.
     * @param TesseraEvents $tesseraEvents Writes Tessera's events.
     */
    public function __construct(
        private readonly ProfileReader $profileReader,
        private readonly string $browser,
        private string $from,
        private bool $resumed,
        private readonly TesseraEvents $tesseraEvents = new TesseraEvents(),
    ) {
    }

    /**
     * @return ServerEvent The page's figures, the next exchange as an appended link, a keep-alive, or the request to
     *                     reconnect.
     */
    #[Override]
    public function next(): ServerEvent
    {
        if ($this->browser === '' || $this->from === '') {
            return $this->tesseraEvents->keepAlive(true);
        }

        if ($this->pending === [] && $this->reads >= self::READS) {
            return $this->tesseraEvents->reconnect();
        }

        $profileRow = $this->upcoming();

        return $profileRow instanceof ProfileRow
            ? $this->event($profileRow)
            : $this->tesseraEvents->keepAlive(false);
    }

    /**
     * @return ProfileRow|null The next profile to send, read when none is pending; null when none is new.
     */
    private function upcoming(): ?ProfileRow
    {
        if ($this->pending === []) {
            $this->pending = $this->read();
        }

        $profileRow = array_shift($this->pending);

        if ($profileRow instanceof ProfileRow && $this->resumed && $profileRow->id === $this->from) {
            $profileRow = array_shift($this->pending);
        }

        return $profileRow;
    }

    /**
     * @param ProfileRow $profileRow The profile to send.
     *
     * @return ServerEvent It as the summary when it is the page's own, else as a link; a keep-alive if it cannot be
     *                     written.
     */
    private function event(ProfileRow $profileRow): ServerEvent
    {
        $page = !$this->resumed && $profileRow->id === $this->from;
        $this->from = $profileRow->id;
        $this->resumed = true;
        $label = $this->label($profileRow);

        try {
            if ($page) {
                $badge = new Badge($label, '');

                return $this->tesseraEvents->patchElements('@' . ProfilerToolbar::SUMMARY, $badge, 'inner', $profileRow->id, false);
            }

            $link = new Link($label, '/_profiler/' . $profileRow->id);

            return $this->tesseraEvents->patchElements('@' . ProfilerToolbar::EXCHANGES, $link, 'append', $profileRow->id, false);
        } catch (InvalidArgumentException) {
            return $this->tesseraEvents->keepAlive(false);
        }
    }

    /**
     * @return list<ProfileRow> The profiles from the last one sent on, after a second's wait on every read but the
     *                          first; none when the table cannot be read.
     */
    private function read(): array
    {
        if ($this->reads > 0) {
            usleep(1_000_000);
        }

        $this->reads += 1;

        try {
            return $this->profileReader->newer($this->browser, $this->from);
        } catch (QueryException) {
            return [];
        }
    }

    /**
     * @param ProfileRow $profileRow A profile.
     *
     * @return string Its exchange in a line: the Tessera request or a load, the method, path and targets, and its
     *                figures.
     */
    private function label(ProfileRow $profileRow): string
    {
        return sprintf(
            '%s %s %s%s · %d · %.1f ms · %d SQL · %d bytes',
            $profileRow->tessera === '' ? 'load' : $profileRow->tessera,
            $profileRow->method,
            $profileRow->path,
            $profileRow->targets === '' ? '' : ' → ' . $profileRow->targets,
            $profileRow->status,
            $profileRow->microseconds / 1_000,
            $profileRow->statements,
            $profileRow->bytes,
        );
    }
}
