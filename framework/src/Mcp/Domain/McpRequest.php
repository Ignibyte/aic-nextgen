<?php

declare(strict_types=1);

namespace AIC\Mcp\Domain;

use AIC\Mcp\Contracts\McpArguments;

use function array_is_list;
use function array_key_exists;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function sprintf;

/**
 * One JSON-RPC message from an MCP client (#316), read from a POST's body: its
 * id (none for a notification), its method and its params, kept as their JSON
 * object's text and read one field at a time. MCP 2025-06-18 sends one message
 * per POST, so a batch is refused.
 */
final readonly class McpRequest
{
    private int|string|null $id;

    private string $method;

    private string $params;

    /**
     * @param string $body The POST's body.
     *
     * @throws McpProtocolException When it is not JSON (-32700) or not one JSON-RPC 2.0 message (-32600).
     */
    public function __construct(string $body)
    {
        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            throw new McpProtocolException('Parse error: the body is not JSON.', McpProtocolException::PARSE_ERROR);
        }

        if (array_is_list($decoded) || !array_key_exists('jsonrpc', $decoded) || $decoded['jsonrpc'] !== '2.0') {
            throw self::invalid('the body must be one JSON-RPC 2.0 message, never a batch');
        }

        $this->id = $this->decodedId($body);
        $this->method = $this->decodedMethod($body);
        $this->params = $this->member($body, 'params', self::invalid('its params must be an object'));
    }

    /**
     * @return int|string|null Its id; null for a notification, a message that gets no reply.
     */
    public function messageId(): int|string|null
    {
        return $this->id;
    }

    /**
     * @return string The method it calls.
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * @param string $name A param's name.
     *
     * @throws McpProtocolException When the params hold no such string (-32602).
     *
     * @return string Its value.
     */
    public function param(string $name): string
    {
        $value = $this->optional($name);

        if ($value === '') {
            throw new McpProtocolException(sprintf('Invalid params: "%s" must be a string.', $name), McpProtocolException::INVALID_PARAMS);
        }

        return $value;
    }

    /**
     * @param string $name A param's name.
     *
     * @return string Its value when it is a string; empty otherwise.
     */
    public function optional(string $name): string
    {
        $params = json_decode($this->params, true);

        if (!is_array($params) || !array_key_exists($name, $params) || !is_string($params[$name])) {
            return '';
        }

        return $params[$name];
    }

    /**
     * @throws McpProtocolException When `arguments` is there and not an object (-32602).
     *
     * @return McpArguments The `arguments` object; none when it is absent.
     */
    public function arguments(): McpArguments
    {
        $mcpProtocolException = new McpProtocolException(
            'Invalid params: "arguments" must be an object.',
            McpProtocolException::INVALID_PARAMS,
        );

        return new McpArguments($this->member($this->params, 'arguments', $mcpProtocolException));
    }

    /**
     * @param string $body The message's JSON object.
     *
     * @throws McpProtocolException When its id is null or neither a string nor an integer.
     *
     * @return int|string|null Its id; null when it has none.
     */
    private function decodedId(string $body): int|string|null
    {
        $decoded = json_decode($body, true);

        if (!is_array($decoded) || !array_key_exists('id', $decoded)) {
            return null;
        }

        if (!is_int($decoded['id']) && !is_string($decoded['id'])) {
            throw self::invalid('its id must be a string or an integer');
        }

        return $decoded['id'];
    }

    /**
     * @param string $body The message's JSON object.
     *
     * @throws McpProtocolException When it names no method.
     *
     * @return string Its method.
     */
    private function decodedMethod(string $body): string
    {
        $decoded = json_decode($body, true);

        if (!is_array($decoded) || !array_key_exists('method', $decoded) || !is_string($decoded['method'])) {
            throw self::invalid('it names no method');
        }

        return $decoded['method'];
    }

    /**
     * @param string               $json                 A JSON object.
     * @param string               $name                 One of its fields, which must be an object when it is there.
     * @param McpProtocolException $mcpProtocolException What to throw when it is not.
     *
     * @throws McpProtocolException When the field is there and not an object.
     *
     * @return string The field's JSON object; an empty object when it is absent or empty.
     */
    private function member(string $json, string $name, McpProtocolException $mcpProtocolException): string
    {
        $decoded = json_decode($json, true);

        if (!is_array($decoded) || !array_key_exists($name, $decoded)) {
            return '{}';
        }

        $member = (string) json_encode($decoded[$name]);
        $value = json_decode($member, true);

        if (!is_array($value)) {
            throw $mcpProtocolException;
        }

        if ($value === []) {
            return '{}';
        }

        return array_is_list($value)
            ? throw $mcpProtocolException
            : $member;
    }

    /**
     * @param string $why What is wrong.
     *
     * @return McpProtocolException The invalid request refusal.
     */
    private static function invalid(string $why): McpProtocolException
    {
        return new McpProtocolException('Invalid request: ' . $why . '.', McpProtocolException::INVALID_REQUEST);
    }
}
