<?php

declare(strict_types=1);

namespace AIC\Mcp\Contracts;

/**
 * A tool an app offers agents over MCP (#316): a readonly class of this type in
 * a module's `src/Mcp/`, marked `#[ToolInfo]`, built with its services as any
 * module class is. It has one public method, `call()`, which takes a readonly
 * input class and, when it asks, the caller's `Actor`, and returns a readonly
 * output class. The build reads that signature, writes the tool's input and
 * output JSON schemas from the two constructors, and writes the code that
 * checks and decodes a call's arguments into the input, so the interface
 * declares no method of its own.
 *
 * @api
 */
interface McpTool
{
}
