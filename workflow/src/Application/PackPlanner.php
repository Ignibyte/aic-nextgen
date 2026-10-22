<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\InstallAction;
use AIC\Workflow\Domain\InstallLine;
use AIC\Workflow\Domain\PackLock;

use function hash;
use function sprintf;

/**
 * The lock's decision table (#105, Droost's drift rule with the lock kept in
 * the project): what init does with an owned path, given the bytes there,
 * the bytes the package ships and the hash the lock holds. An unedited file
 * is upgraded, an edited one kept, a retired one removed while unedited.
 * Only the operator's `--force` overrides an edit.
 */
final readonly class PackPlanner
{
    /**
     * Why an edited file is kept.
     */
    private const string EDITED = "edited since init wrote it; the operator's `bin/aic workflow:init --force=%s` takes the shipped one.";

    /**
     * Why a file init did not write is kept.
     */
    private const string FOREIGN = "not written by init; the operator's `bin/aic workflow:init --force=%s` takes the shipped one.";

    /**
     * @param string      $path     The path, as the lock keys it.
     * @param string|null $disk     What is there; null for nothing.
     * @param string      $shipped  What the package ships.
     * @param PackLock    $packLock The lock so far.
     * @param bool        $forced   Whether the operator forces it.
     *
     * @return InstallLine What to do: wrote means write the shipped bytes.
     */
    public function owned(string $path, ?string $disk, string $shipped, PackLock $packLock, bool $forced): InstallLine
    {
        $locked = $packLock->hash($path);

        return match (true) {
            $disk === $shipped => new InstallLine(InstallAction::Current, $path),
            $disk === null => new InstallLine(InstallAction::Wrote, $path),
            $forced => new InstallLine(InstallAction::Wrote, $path, 'forced by the operator'),
            $locked === $this->hash($disk) => new InstallLine(InstallAction::Wrote, $path, 'upgraded'),
            default => new InstallLine(InstallAction::Kept, $path, sprintf($locked === null ? self::FOREIGN : self::EDITED, $path)),
        };
    }

    /**
     * @param string      $path     A path the lock lists and the pack no longer ships.
     * @param string|null $disk     What is there; null for nothing.
     * @param PackLock    $packLock The lock so far.
     * @param bool        $forced   Whether the operator forces it.
     *
     * @return InstallLine Removed when it is gone or unedited (or forced); kept when edited.
     */
    public function retired(string $path, ?string $disk, PackLock $packLock, bool $forced): InstallLine
    {
        return match (true) {
            $disk === null => new InstallLine(InstallAction::Removed, $path, 'retired by aic/workflow; already gone'),
            $forced || $packLock->hash($path) === $this->hash($disk) => new InstallLine(
                InstallAction::Removed,
                $path,
                'retired by aic/workflow',
            ),
            default => new InstallLine(InstallAction::Kept, $path, 'retired by aic/workflow and edited since; delete it by hand'),
        };
    }

    /**
     * @param InstallLine $installLine What init did with an owned path.
     * @param string      $shipped     What the package ships there.
     * @param PackLock    $packLock    The lock so far.
     *
     * @return PackLock The lock after it: the shipped hash once written or current, unchanged when kept.
     */
    public function lockAfter(InstallLine $installLine, string $shipped, PackLock $packLock): PackLock
    {
        return match ($installLine->installAction) {
            InstallAction::Wrote, InstallAction::Current => $packLock->with($installLine->path, $this->hash($shipped)),
            InstallAction::Removed => $packLock->without($installLine->path),
            InstallAction::Kept => $packLock,
        };
    }

    /**
     * @param string $bytes Some bytes.
     *
     * @return string Their lock hash, `sha256:<hex>`.
     */
    public function hash(string $bytes): string
    {
        return 'sha256:' . hash('sha256', $bytes);
    }
}
