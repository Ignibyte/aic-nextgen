    /**
     * @throws InvalidArgumentException When a field is invalid.
     *
     * @return EntityType The `{table}` pivot, made by bin/aic make:relation.
     */
    public function {variable}(): EntityType
    {
        return new EntityType('{table}', 'id', [
{fields}        ]);
    }
