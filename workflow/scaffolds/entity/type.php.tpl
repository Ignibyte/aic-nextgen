    /**
     * @param list<object> $relations Its Relations.
     *
     * @throws InvalidArgumentException When a field is invalid.
     *
     * @return EntityType The `{table}` table.
     */
    private function {variable}With(array $relations): EntityType
    {
        return new EntityType('{table}', 'id', [
{fields}        ]{keyType}, relations: $relations);
    }
