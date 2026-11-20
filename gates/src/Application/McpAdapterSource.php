<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Mcp\Attribute\ResourceInfo;
use AIC\Mcp\Contracts\McpResource;
use AIC\Mcp\Domain\McpResourceInfo;
use AIC\Mcp\Domain\McpResourceRead;
use InvalidArgumentException;
use Override;

use function strtr;

/**
 * Writes a resource's adapter (#316), `<Resource>Read`: built with the resource
 * by the module's catalog, it gives the descriptor from `#[ResourceInfo]` and
 * passes the resource's text through.
 */
final readonly class McpAdapterSource
{
    private const string READ = <<<'PHP'
        final readonly class {class} implements McpResourceRead
        {
            /**
             * @param McpResource $mcpResource The resource.
             */
            public function __construct(
                private McpResource $mcpResource,
            ) {
            }

            /**
             * @return McpResourceInfo The resource, from its #[ResourceInfo].
             */
            #[Override]
            public function resourceInfo(): McpResourceInfo
            {
                return new McpResourceInfo(
                    {uri},
                    {name},
                    {description},
                    {mimeType},
                    {needs},
                );
            }

            /**
             * @return string Its text now.
             */
            #[Override]
            public function resourceText(): string
            {
                return $this->mcpResource->read();
            }
        }
        PHP;

    /**
     * @param PhpClassFile $phpClassFile Emits checked generated source.
     * @param McpCode      $mcpCode      The code pieces.
     */
    public function __construct(
        private PhpClassFile $phpClassFile = new PhpClassFile(),
        private McpCode $mcpCode = new McpCode(),
    ) {
    }

    /**
     * @param class-string $class        A resource.
     * @param ResourceInfo $resourceInfo Its attribute.
     * @param string       $from         Its module's `src/Mcp/`.
     *
     * @throws InvalidArgumentException When imports conflict.
     *
     * @return string Its adapter's file.
     */
    public function read(string $class, ResourceInfo $resourceInfo, string $from): string
    {
        $source = strtr(self::READ, [
            '{class}' => $this->mcpCode->short($class) . 'Read',
            '{description}' => $this->mcpCode->value($resourceInfo->description),
            '{mimeType}' => $this->mcpCode->value($resourceInfo->mimeType),
            '{name}' => $this->mcpCode->value($resourceInfo->name),
            '{needs}' => $this->mcpCode->value($resourceInfo->needs),
            '{uri}' => $this->mcpCode->value($resourceInfo->uri),
        ]);
        $docblock = $this->phpClassFile->generated($from, 'the ' . $resourceInfo->uri . " resource's adapter (#316).");

        $classes = [McpResource::class, McpResourceInfo::class, McpResourceRead::class, Override::class];

        return $this->phpClassFile->file('App\\Generated', $classes, [], $docblock, $source);
    }
}
