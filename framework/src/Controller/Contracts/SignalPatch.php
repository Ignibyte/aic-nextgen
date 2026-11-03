<?php

declare(strict_types=1);

namespace AIC\Controller\Contracts;

use InvalidArgumentException;

use function json_validate;
use function ltrim;
use function preg_match;
use function str_replace;
use function str_starts_with;

/**
 * One signal patch a Page's Tessera fragment answer carries (#253, protocol
 * 5.3): a strict JSON object merged into the named scope after every
 * fragment lands, setting only the signals that do not exist yet when
 * `onlyIfMissing` is set. A full document carries none; its markup sets the
 * signals a page starts with. The response writer writes `<` as `<`.
 */
final readonly class SignalPatch
{
    /** A target: `page`, a region, or a component's path (protocol 2.6). */
    public const string TARGET = '#^[a-z][a-z0-9]*(-[a-z0-9]+)*(/[A-Za-z0-9_.:]+(-[A-Za-z0-9_.:]+)*)*$#';

    /** An anchor as a target (#204, Tessera 0.3.1): `@` and a name in the key grammar, up to 64. */
    public const string ANCHOR = '#^@(?=.{1,64}$)[A-Za-z0-9_.:]+(-[A-Za-z0-9_.:]+)*$#';

    /**
     * @param string $scope         The target whose signal scope it patches, `@NAME` included; `page` for the document.
     * @param string $json          A strict JSON object.
     * @param bool   $onlyIfMissing Whether it sets only signals that do not exist yet.
     *
     * @throws InvalidArgumentException When the scope is not a target or the JSON is not an object.
     */
    public function __construct(
        public string $scope,
        public string $json,
        public bool $onlyIfMissing = false,
    ) {
        if (preg_match(self::TARGET, $scope) !== 1 && preg_match(self::ANCHOR, $scope) !== 1) {
            throw new InvalidArgumentException('A signal patch names a target as its scope.');
        }

        if (!json_validate($json) || !str_starts_with(ltrim($json), '{')) {
            throw new InvalidArgumentException('A signal patch is a strict JSON object.');
        }
    }

    /**
     * @return array{scope: string, json: string, onlyIfMissing: bool} It as the fragment template writes it, `<` in its
     *                                                                 JSON as `\u003c` so no `</script` ends it.
     */
    public function scripted(): array
    {
        return ['scope' => $this->scope, 'json' => str_replace('<', '\\u003c', $this->json), 'onlyIfMissing' => $this->onlyIfMissing];
    }
}
