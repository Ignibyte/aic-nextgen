<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Make;

use AIC\Workflow\Domain\Console\CommandUsage;

/**
 * The plain PHP shapes a make writes (#319), each in the folder gate:26 gives its kind: an enum in `src/Enum/`, an
 * exception in `src/Exception/`, an interface in `src/Contracts/`.
 */
enum MadeShape
{
    case Enum;

    case Exception;

    case Interface;

    /**
     * What `bin/aic make:enum` takes.
     */
    private const string ENUM_USAGE = 'make:enum <module> <Name> --cases=<a>,<b>,… [--backed=string|int] [--dry-run] [--json]';

    /**
     * What `bin/aic make:interface` takes.
     */
    private const string INTERFACE_USAGE = 'make:interface <module> <Name> [--dry-run] [--json]';

    /**
     * What `bin/aic make:exception` takes.
     */
    private const string EXCEPTION_USAGE = 'make:exception <module> <Name>Exception [--extends=LogicException|InvalidArgumentException]'
        . ' [--dry-run] [--json]';

    /**
     * @return CommandUsage The line its make takes: a module and a name, the dry-run flag, and its own options.
     */
    public function usage(): CommandUsage
    {
        return match ($this) {
            self::Enum => new CommandUsage('make:enum', self::ENUM_USAGE, ['dry-run'], ['backed', 'cases'], 2),
            self::Exception => new CommandUsage('make:exception', self::EXCEPTION_USAGE, ['dry-run'], ['extends'], 2),
            self::Interface => new CommandUsage('make:interface', self::INTERFACE_USAGE, ['dry-run'], [], 2),
        };
    }

    /**
     * @return string Its folder under a module's `src/`.
     */
    public function folder(): string
    {
        return match ($this) {
            self::Enum => 'Enum',
            self::Exception => 'Exception',
            self::Interface => 'Contracts',
        };
    }
}
