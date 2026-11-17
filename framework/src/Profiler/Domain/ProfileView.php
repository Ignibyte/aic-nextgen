<?php

declare(strict_types=1);

namespace AIC\Profiler\Domain;

/**
 * One recorded request as the profiler's detail page shows it (#230).
 */
final readonly class ProfileView
{
    /**
     * @param ProfileRow            $profileRow    Its list row.
     * @param string                $handler       The handler's class.
     * @param string                $user          The signed-in user's id.
     * @param ProfileTimes          $profileTimes  Where its time went.
     * @param ProfileRecords        $records       Its statements, templates and calls (#305), each in order.
     * @param array<string, string> $tessera       Its Tessera request headers.
     * @param array<string, string> $answer        Its answer's listed headers.
     * @param RequestDetail         $requestDetail Its header and field names, and a Tessera answer's markup.
     * @param string                $previous      The same browser's request before it; empty for none.
     * @param int                   $fullBytes     The newest whole-document answer for the same path, in bytes; 0 for none.
     */
    public function __construct(
        public ProfileRow $profileRow,
        public string $handler,
        public string $user,
        public ProfileTimes $profileTimes,
        public ProfileRecords $records,
        public array $tessera,
        public array $answer,
        public RequestDetail $requestDetail,
        public string $previous,
        public int $fullBytes,
    ) {
    }
}
