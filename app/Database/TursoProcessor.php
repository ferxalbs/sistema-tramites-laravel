<?php

namespace App\Database;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\SQLiteProcessor;
use LogicException;
use RuntimeException;

class TursoProcessor extends SQLiteProcessor
{
    /**
     * Return an inserted ID from the same remote transaction that performed the insert.
     *
     * @param  string  $sql
     * @param  array<int|string, mixed>  $values
     */
    #[\Override]
    public function processInsertGetId(Builder $query, mixed $sql, mixed $values, mixed $sequence = null): int
    {
        if (! is_string($sql) || ! is_array($values)) {
            throw new RuntimeException('Invalid libSQL insert arguments.');
        }

        $connection = $query->getConnection();

        if (! $connection instanceof TursoConnection) {
            throw new LogicException('Turso insert processing requires a Turso HTTP connection.');
        }

        return $connection->insertAndGetId($sql, $values);
    }
}
