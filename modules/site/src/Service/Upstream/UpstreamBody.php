<?php

declare(strict_types=1);

namespace App\Site\Service\Upstream;

use JsonSerializable;
use Override;

/**
 * GET /upstream's body (#305): the status each call to the stub got, 0 for
 * none, and what the stub echoed.
 */
final readonly class UpstreamBody implements JsonSerializable
{
    /**
     * @param int    $echo    The made client's call's status.
     * @param string $echoed  What the stub echoed of it.
     * @param int    $fast    The pooled call with time to spare.
     * @param int    $slow    The pooled call that ran out of time.
     * @param int    $retried The retried call's last status.
     */
    public function __construct(
        private int $echo,
        private string $echoed,
        private int $fast,
        private int $slow,
        private int $retried,
    ) {
    }

    /**
     * @return array<string, int|string> Each status by call, and the echo.
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'echo' => $this->echo,
            'echoed' => $this->echoed,
            'fast' => $this->fast,
            'retried' => $this->retried,
            'slow' => $this->slow,
        ];
    }
}
