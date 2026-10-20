<?php

declare(strict_types=1);

namespace Gates\Domain;

use function preg_match;
use function str_ends_with;
use function str_replace;

/**
 * What one site's run of gate:23 remembers between steps (#42): the session
 * cookie it last set, the CSRF token and the signed envelope its last page
 * carried, and the note the capturing step created. Each site gets its own.
 */
final class NativeSiteSession
{
    /**
     * The session cookie, `name=value`; empty before the first one.
     */
    private string $cookie = '';

    /**
     * The CSRF token the last form carried.
     */
    private string $token = '';

    /**
     * The signed envelope the last form carried.
     */
    private string $envelope = '';

    /**
     * The captured note's path.
     */
    private string $note = '/notes/0';

    /**
     * @param NativeSiteStep $nativeSiteStep A step.
     *
     * @return string Its path, `{note}` replaced.
     */
    public function path(NativeSiteStep $nativeSiteStep): string
    {
        return str_replace('{note}', $this->note, $nativeSiteStep->path);
    }

    /**
     * @param NativeSiteStep $nativeSiteStep A step.
     *
     * @return string Its form, `{csrf}` and `{envelope}` replaced by the token and the envelope.
     */
    public function form(NativeSiteStep $nativeSiteStep): string
    {
        return str_replace('{envelope}', $this->envelope, str_replace('{csrf}', $this->token, $nativeSiteStep->form));
    }

    /**
     * @param NativeSiteStep $nativeSiteStep A step.
     *
     * @return string Its own header, or else the session cookie; empty for neither.
     */
    public function header(NativeSiteStep $nativeSiteStep): string
    {
        if ($nativeSiteStep->header !== '' || $this->cookie === '') {
            return $nativeSiteStep->header;
        }

        return 'Cookie: ' . $this->cookie;
    }

    /**
     * Keeps what the answer carried: a new cookie (a cleared one forgets the
     * session), the token in its body, and its location when the step captures.
     *
     * @param NativeSiteStep $nativeSiteStep The step.
     * @param HttpExchange   $httpExchange   Its answer.
     * @param string         $location       The answer's location, as a path.
     */
    public function absorb(NativeSiteStep $nativeSiteStep, HttpExchange $httpExchange, string $location): void
    {
        if ($httpExchange->cookie !== '') {
            $this->cookie = str_ends_with($httpExchange->cookie, '=')
                ? ''
                : $httpExchange->cookie;
        }

        $this->token = self::captured('/name="aic_csrf" value="([0-9a-f]{64})"/', $httpExchange->body, $this->token);
        $this->envelope = self::captured('/name="aic_envelope" value="([\\w.-]+)"/', $httpExchange->body, $this->envelope);

        if (!$nativeSiteStep->captures) {
            return;
        }

        $this->note = $location;
    }

    /**
     * @param string $pattern A pattern with one group.
     * @param string $body    A page.
     * @param string $current The value kept so far.
     *
     * @return string The group's match in the page; the kept value when it has none.
     */
    private static function captured(string $pattern, string $body, string $current): string
    {
        return preg_match($pattern, $body, $match) === 1
            ? $match[1]
            : $current;
    }
}
