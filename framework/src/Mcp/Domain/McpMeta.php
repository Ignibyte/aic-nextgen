<?php

declare(strict_types=1);

namespace AIC\Mcp\Domain;

use function array_key_exists;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;

/**
 * A request's `params._meta` (#332, #333), read one key at a time from the
 * params' JSON: AIC's `aic/callId` and `aic/dryRun`, and MCP's
 * `progressToken`.
 */
final readonly class McpMeta
{
    /**
     * @param string  $params  The request's params, as their JSON object's text.
     * @param McpJson $mcpJson Writes a string token as JSON.
     */
    public function __construct(
        private string $params,
        private McpJson $mcpJson = new McpJson(),
    ) {
    }

    /**
     * @param string $name A key of `params._meta`, such as `aic/callId` (#332).
     *
     * @return string Its value when it is a string; empty otherwise.
     */
    public function text(string $name): string
    {
        $params = json_decode($this->params, true);

        if (
            !is_array($params)
            || !array_key_exists('_meta', $params)
            || !is_array($params['_meta'])
            || !array_key_exists($name, $params['_meta'])
        ) {
            return '';
        }

        return is_string($params['_meta'][$name])
            ? $params['_meta'][$name]
            : '';
    }

    /**
     * @return string `params._meta.progressToken` as JSON, a string or an integer (#333); empty when it is absent
     *                or neither.
     */
    public function progressToken(): string
    {
        $text = $this->text('progressToken');

        if ($text !== '') {
            return $this->mcpJson->text($text);
        }

        $number = $this->number('progressToken');

        return $number === null
            ? ''
            : (string) $number;
    }

    /**
     * @param string $name A key of `params._meta`, such as `aic/dryRun` (#332).
     *
     * @return bool Whether it is `true`.
     */
    public function flag(string $name): bool
    {
        $params = json_decode($this->params, true);

        if (!is_array($params) || !array_key_exists('_meta', $params) || !is_array($params['_meta'])) {
            return false;
        }

        return array_key_exists($name, $params['_meta']) && $params['_meta'][$name] === true;
    }

    /**
     * @param string $name A key of `params._meta`.
     *
     * @return int|null Its value when it is an integer; null otherwise.
     */
    private function number(string $name): ?int
    {
        $params = json_decode($this->params, true);

        if (!is_array($params) || !array_key_exists('_meta', $params) || !is_array($params['_meta'])) {
            return null;
        }

        return array_key_exists($name, $params['_meta']) && is_int($params['_meta'][$name])
            ? $params['_meta'][$name]
            : null;
    }
}
