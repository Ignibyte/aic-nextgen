<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * Outcome of one native probe (gate:21): compile, link, run, compare with PHP.
 *
 * `Ok` and `Cached` pass: `Cached` is a class whose closure and toolchain
 * match a green probe of an earlier run, so it was not probed again. The
 * others name the first step that went wrong, so a failing root says where to
 * look: the compiler, the linker, the native binary, or a silent miscompile.
 */
enum NativeProbeStatus: string
{
    case Ok = 'OK';
    case Cached = 'CACHED';
    case CompileFail = 'COMPILE_FAIL';
    case LinkFail = 'LINK_FAIL';
    case RunFail = 'RUN_FAIL';
    case Mismatch = 'MISMATCH';
}
