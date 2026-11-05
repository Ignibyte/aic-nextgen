<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\SeekerChecklist;
use AIC\Workflow\Domain\RegexCaptures;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;

use function file_get_contents;
use function is_file;
use function is_string;
use function realpath;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * The AIC checklist as the package ships it (#103): `pack/seeker/checklist.md`,
 * each item a `## <id> — <title>` heading. Its path is given relative to the
 * project: inside it where the package's source is, under the vendor
 * directory in an app.
 */
final readonly class SeekerChecklistFile implements SeekerChecklist
{
    use RegexCaptures;

    /**
     * The checklist inside the package.
     */
    private const string FILE = 'pack/seeker/checklist.md';

    /**
     * @param string $root    The project's root.
     * @param string $package The package's root.
     * @param string $vendor  The project's vendor directory.
     */
    public function __construct(
        private string $root,
        private string $package,
        private string $vendor,
    ) {
    }

    /**
     * @throws WorkflowRefusal When it is missing or holds no item.
     *
     * @return list<string> Its item ids.
     */
    #[Override]
    public function ids(): array
    {
        $path = $this->package . '/' . self::FILE;
        $text = is_file($path)
            ? file_get_contents($path)
            : false;

        $items = is_string($text)
            ? self::regexColumn('/^## ([A-Z]\\d+) — /m', $text, 1)
            : [];

        if ($items === []) {
            throw new WorkflowRefusal(
                'the seeker checklist (' . $this->path() . ') is missing or names no item; reinstall ignibyte/aic-workflow.',
            );
        }

        return $items;
    }

    /**
     * @return string Its path, relative to the project.
     */
    #[Override]
    public function path(): string
    {
        $root = realpath($this->root);
        $package = realpath($this->package);

        return is_string($root) && is_string($package) && str_starts_with($package . '/', $root . '/')
            ? substr($package, strlen($root) + 1) . '/' . self::FILE
            : $this->vendor . '/ignibyte/aic-workflow/' . self::FILE;
    }
}
