<?php

declare(strict_types=1);

namespace AIC\HttpClient\Application;

use AIC\HttpClient\Contracts\AnswerException;
use AIC\HttpClient\Contracts\PooledAnswer;
use AIC\HttpClient\Contracts\PooledRequest;
use AIC\HttpClient\Contracts\RequestException;
use AIC\HttpClient\Contracts\RequestPool;
use AIC\Kernel\Domain\Monotonic;
use CurlHandle;
use CurlMultiHandle;
use Override;
use Psr\Http\Message\RequestInterface;

use function array_key_exists;
use function count;
use function curl_multi_add_handle;
use function curl_multi_exec;
use function curl_multi_getcontent;
use function curl_multi_info_read;
use function curl_multi_init;
use function curl_multi_remove_handle;
use function curl_multi_select;
use function is_int;

use const CURLE_OK;
use const CURLM_OK;

/**
 * The pool over `curl_multi` (#305): every request is added to one multi
 * handle, which runs them together until curl has reported each one done;
 * each keeps its own time limit, and one that fails leaves the others alone.
 * A request's time runs from the start to the moment curl reported it.
 */
final readonly class CurlPool implements RequestPool
{
    /** Seconds to wait for activity between runs of the multi handle. */
    private const float SELECT_SECONDS = 0.05;

    /**
     * @param CurlTransfer $curlTransfer Turns a request into a handle and its answer into a response.
     * @param Monotonic    $monotonic    Times each request.
     */
    public function __construct(
        private CurlTransfer $curlTransfer = new CurlTransfer(),
        private Monotonic $monotonic = new Monotonic(),
    ) {
    }

    /**
     * @param array<string, PooledRequest> $requests The requests, each under a name.
     *
     * @throws RequestException When a request cannot be sent at all; none is then sent.
     *
     * @return array<string, PooledAnswer> What each got, under its name, in the order given.
     */
    #[Override]
    public function send(array $requests): array
    {
        $curlMultiHandle = curl_multi_init();
        $handles = [];

        foreach ($requests as $name => $pooledRequest) {
            $handles[$name] = $this->curlTransfer->handle($pooledRequest->request, $pooledRequest->timeoutMs);
        }

        foreach ($handles as $handle) {
            curl_multi_add_handle($curlMultiHandle, $handle);
        }

        $done = $this->run($curlMultiHandle, $handles, $this->monotonic->microseconds());
        $answers = [];

        foreach ($requests as $name => $pooledRequest) {
            $answers[$name] = $this->answer($pooledRequest->request, $handles[$name], $done[$name] ?? [CURLE_OK, 0]);
            curl_multi_remove_handle($curlMultiHandle, $handles[$name]);
        }

        return $answers;
    }

    /**
     * @param CurlMultiHandle           $curlMultiHandle The multi handle, holding every request's handle.
     * @param array<string, CurlHandle> $handles         Each request's handle, by name.
     * @param int                       $started         When they started.
     *
     * @return array<string, array{int, int}> Each request's curl code and time, by name, once curl reports it done.
     */
    private function run(CurlMultiHandle $curlMultiHandle, array $handles, int $started): array
    {
        $running = 0;
        $done = [];

        do {
            $status = curl_multi_exec($curlMultiHandle, $running);
            $done = $this->finished($curlMultiHandle, $handles, $done, $started);
        } while ($this->waited($curlMultiHandle, count($done) < count($handles) && $status === CURLM_OK));

        return $done;
    }

    /**
     * @param CurlMultiHandle $curlMultiHandle The multi handle.
     * @param bool            $waiting         Whether a request is still running and curl is well.
     *
     * @return bool Whether to run the multi handle again, after waiting for activity on it.
     */
    private function waited(CurlMultiHandle $curlMultiHandle, bool $waiting): bool
    {
        if (!$waiting) {
            return false;
        }

        curl_multi_select($curlMultiHandle, self::SELECT_SECONDS);

        return true;
    }

    /**
     * @param CurlMultiHandle                $curlMultiHandle The multi handle.
     * @param array<string, CurlHandle>      $handles         Each request's handle, by name.
     * @param array<string, array{int, int}> $done            The requests reported so far.
     * @param int                            $started         When they started.
     *
     * @return array<string, array{int, int}> Those, and each request curl has reported since, with its code and time.
     */
    private function finished(CurlMultiHandle $curlMultiHandle, array $handles, array $done, int $started): array
    {
        $queued = 0;
        $info = curl_multi_info_read($curlMultiHandle, $queued);

        while ($info !== false) {
            if (
                array_key_exists('handle', $info)
                && array_key_exists('result', $info)
                && $info['handle'] instanceof CurlHandle
                && is_int($info['result'])
            ) {
                $elapsed = $this->monotonic->microseconds() - $started;
                $done = $this->reported($handles, $done, $info['handle'], [$info['result'], $elapsed]);
            }

            $info = curl_multi_info_read($curlMultiHandle, $queued);
        }

        return $done;
    }

    /**
     * @param array<string, CurlHandle>      $handles    Each request's handle, by name.
     * @param array<string, array{int, int}> $done       The requests reported so far.
     * @param CurlHandle                     $curlHandle The handle curl reported.
     * @param array{int, int}                $outcome    Its curl code and time.
     *
     * @return array<string, array{int, int}> The reports, with its.
     */
    private function reported(array $handles, array $done, CurlHandle $curlHandle, array $outcome): array
    {
        foreach ($handles as $name => $handle) {
            if ($handle !== $curlHandle) {
                continue;
            }

            $done[$name] = $outcome;
        }

        return $done;
    }

    /**
     * @param RequestInterface $request    The request.
     * @param CurlHandle       $curlHandle Its handle, run.
     * @param array{int, int}  $outcome    Its curl code and time.
     *
     * @return PooledAnswer Its response, or why it has none.
     */
    private function answer(RequestInterface $request, CurlHandle $curlHandle, array $outcome): PooledAnswer
    {
        [$code, $microseconds] = $outcome;

        if ($code !== CURLE_OK) {
            return new PooledAnswer($request, null, $this->curlTransfer->failure($code), $microseconds);
        }

        try {
            $response = $this->curlTransfer->response(curl_multi_getcontent($curlHandle) ?? '');
        } catch (AnswerException $answerException) {
            return new PooledAnswer($request, null, $answerException->getMessage(), $microseconds);
        }

        return new PooledAnswer($request, $response, '', $microseconds);
    }
}
