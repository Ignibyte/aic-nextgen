<?php

declare(strict_types=1);

namespace Gates\Sniffs\Functions;

use Gates\Domain\KindMethods;
use Gates\Sniffs\ImportedNames;
use Override;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use SlevomatCodingStandard\Sniffs\Functions\UnusedParameterSniff;

use function is_array;
use function is_int;
use function is_string;

use const T_CLASS;
use const T_FUNCTION;

/**
 * Slevomat's Functions.UnusedParameter (gate:11), except on a kind method
 * (#56): a Page, Api, Stream, Command or Job method may leave the parameter
 * its interface dictates unread. The class's `implements` names are resolved
 * to full names before they are compared. PHPCS builds a sniff with no
 * arguments, so the collaborators are made where they are used.
 */
final readonly class KindAwareUnusedParameterSniff implements Sniff
{
    /**
     * @return array<int, int|string> The tokens the stock sniff listens for: functions and closures.
     */
    #[Override]
    public function register(): array
    {
        return (new UnusedParameterSniff())->register();
    }

    /**
     * @param File  $phpcsFile The file.
     * @param mixed $stackPtr  The function's token; PHPCS's interface leaves it untyped.
     */
    #[Override]
    public function process(File $phpcsFile, mixed $stackPtr): void
    {
        if (!is_int($stackPtr) || $this->isKindMethod($phpcsFile, $stackPtr)) {
            return;
        }

        (new UnusedParameterSniff())->process($phpcsFile, $stackPtr);
    }

    /**
     * @param File $phpcsFile       The file.
     * @param int  $functionPointer A function's token.
     *
     * @return bool Whether it is a named method (not a closure or an arrow function) that implements a kind
     *              interface's method.
     */
    private function isKindMethod(File $phpcsFile, int $functionPointer): bool
    {
        $token = $phpcsFile->getTokens()[$functionPointer] ?? null;
        $classPointer = $phpcsFile->getCondition($functionPointer, T_CLASS);

        if (!is_array($token) || ($token['code'] ?? null) !== T_FUNCTION || !is_int($classPointer)) {
            return false;
        }

        $method = $phpcsFile->getDeclarationName($functionPointer);

        return is_string($method)
            && (new KindMethods())->isKindMethod($this->interfaces($phpcsFile, $classPointer), $method);
    }

    /**
     * @param File $phpcsFile    The file.
     * @param int  $classPointer A class keyword.
     *
     * @return list<string> The full names of the interfaces the class's `implements` names.
     */
    private function interfaces(File $phpcsFile, int $classPointer): array
    {
        $written = $phpcsFile->findImplementedInterfaceNames($classPointer);
        $interfaces = [];

        foreach ($written === false ? [] : $written as $name) {
            if (!is_string($name)) {
                continue;
            }

            $interfaces[] = (new ImportedNames())->resolve($phpcsFile, $name, $classPointer);
        }

        return $interfaces;
    }
}
