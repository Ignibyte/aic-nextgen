<?php

declare(strict_types=1);

namespace AIC\Schema\Application;

use AIC\Schema\Domain\GuardKind;
use AIC\Schema\Domain\Identifier;
use AIC\Schema\Domain\TableGuard;
use InvalidArgumentException;

use function array_map;
use function implode;

/**
 * Postgres triggers for a TableGuard (#310). A refused write raises
 * `restrict_violation` (SQLSTATE 23001). The functions' bodies hold no `?` or
 * `:`, so no PDO placeholder parser reads one into them.
 */
final readonly class PostgresGuardDdl
{
    private const string REFUSE = " USING ERRCODE = 'restrict_violation'";

    /**
     * @param TableGuard $tableGuard A guard.
     *
     * @throws InvalidArgumentException When a function or trigger name would be longer than an identifier may be.
     *
     * @return list<string> Its function, then its triggers.
     */
    public function statements(TableGuard $tableGuard): array
    {
        return match ($tableGuard->guardKind) {
            GuardKind::AppendOnly => $this->appendOnly($tableGuard->table),
            GuardKind::FinalOnce => $this->finalOnce($tableGuard),
        };
    }

    /**
     * @param Identifier $identifier The table.
     *
     * @throws InvalidArgumentException When a trigger name would be longer than an identifier may be.
     *
     * @return list<string> The shared `aic_append_only()` function, a row trigger on UPDATE and DELETE, and a
     *                      statement trigger on TRUNCATE.
     */
    private function appendOnly(Identifier $identifier): array
    {
        $table = $identifier->value;

        return [
            'CREATE OR REPLACE FUNCTION aic_append_only() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN '
                . "RAISE EXCEPTION 'The table % is append-only.', TG_TABLE_NAME" . self::REFUSE . '; END $$',
            'CREATE OR REPLACE TRIGGER ' . (new Identifier($table . '_append_only'))->value . ' BEFORE UPDATE OR DELETE ON '
                . $table . ' FOR EACH ROW EXECUTE FUNCTION aic_append_only()',
            'CREATE OR REPLACE TRIGGER ' . (new Identifier($table . '_no_truncate'))->value . ' BEFORE TRUNCATE ON '
                . $table . ' FOR EACH STATEMENT EXECUTE FUNCTION aic_append_only()',
        ];
    }

    /**
     * @param TableGuard $tableGuard A FinalOnce guard.
     *
     * @throws InvalidArgumentException When the function name would be longer than an identifier may be.
     *
     * @return list<string> The table's own `<table>_final_once()` function and its row trigger on UPDATE and DELETE.
     */
    private function finalOnce(TableGuard $tableGuard): array
    {
        $table = $tableGuard->table->value;
        $function = (new Identifier($table . '_final_once'))->value;
        $set = array_map(static fn (Identifier $identifier): string => 'OLD.' . $identifier->value . ' IS NOT NULL', $tableGuard->columns);
        $changed = array_map(
            static fn (Identifier $identifier): string => '(OLD.' . $identifier->value . ' IS NOT NULL AND NEW.' . $identifier->value
                . ' IS DISTINCT FROM OLD.' . $identifier->value . ')',
            $tableGuard->columns,
        );

        return [
            'CREATE OR REPLACE FUNCTION ' . $function . '() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN '
                . "IF TG_OP = 'DELETE' THEN IF " . implode(' OR ', $set) . " THEN RAISE EXCEPTION 'A row of % "
                . "holding a final value cannot be deleted.', TG_TABLE_NAME" . self::REFUSE . '; END IF; RETURN OLD; END IF; '
                . 'IF ' . implode(' OR ', $changed) . " THEN RAISE EXCEPTION 'A final value of % cannot change.', "
                . 'TG_TABLE_NAME' . self::REFUSE . '; END IF; RETURN NEW; END $$',
            'CREATE OR REPLACE TRIGGER ' . $function . ' BEFORE UPDATE OR DELETE ON ' . $table
                . ' FOR EACH ROW EXECUTE FUNCTION ' . $function . '()',
        ];
    }
}
