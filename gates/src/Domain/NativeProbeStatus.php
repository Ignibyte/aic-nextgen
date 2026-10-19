<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * Outcome of one native probe (gate:21): compile, link, run, compare with PHP.
 *
 * Only `Ok` passes. The others name the first step that went wrong, so a
 * failing root says where to look: the compiler, the linker, the native
 * binary, or a silent miscompile.
 *
 * @api
 */
enum NativeProbeStatus: string
{
    case Ok = 'OK';
    case CompileFail = 'COMPILE_FAIL';
    case LinkFail = 'LINK_FAIL';
    case RunFail = 'RUN_FAIL';
    case Mismatch = 'MISMATCH';
}
