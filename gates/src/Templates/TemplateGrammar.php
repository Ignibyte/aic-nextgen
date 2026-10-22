<?php

declare(strict_types=1);

namespace Gates\Templates;

/**
 * gate:25's configuration (#96): where templates live, and the functions a
 * template may call. The list of functions starts empty (Chad, 2026-10-21:
 * "no functions at all to start"); adding one is a decision recorded in
 * plan.md, never a quiet edit here.
 */
final readonly class TemplateGrammar
{
    /**
     * @return list<string> The functions a template may call, lower case; none.
     */
    public function functions(): array
    {
        return [];
    }

    /**
     * @return list<string> The folders searched for `*.html.php`, relative to the repo root.
     */
    public function roots(): array
    {
        return ['modules', 'templates'];
    }

    /**
     * @return list<string> The superglobals, without `$`: a template reads none.
     */
    public function superglobals(): array
    {
        return ['_GET', '_POST', '_COOKIE', '_FILES', '_SERVER', '_ENV', '_REQUEST', '_SESSION', 'GLOBALS'];
    }
}
