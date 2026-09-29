<?php

namespace App\Database;

use Illuminate\Database\Schema\Grammars\SQLiteGrammar;
use InvalidArgumentException;

class TursoSchemaGrammar extends SQLiteGrammar
{
    /**
     * Turso does not accept SQLite's optional schema argument in pragma functions.
     */
    #[\Override]
    public function compileColumns($schema, $table): string
    {
        $this->assertMainSchema($schema);

        return 'select name, type, not "notnull" as "nullable", dflt_value as "default", pk as "primary", hidden as "extra" '
            .'from pragma_table_xinfo('.$this->quoteString($table).') order by cid asc';
    }

    #[\Override]
    public function compileIndexes($schema, $table): string
    {
        $this->assertMainSchema($schema);
        $table = $this->quoteString($table);

        return 'select \'primary\' as name, group_concat(col) as columns, 1 as "unique", 1 as "primary" '
            .'from (select name as col from pragma_table_xinfo('.$table.') where pk > 0 order by pk, cid) group by name '
            .'union select name, group_concat(col) as columns, "unique", origin = \'pk\' as "primary" '
            .'from (select il.*, ii.name as col from pragma_index_list('.$table.') il, pragma_index_info(il.name) ii order by il.seq, ii.seqno) '
            .'group by name, "unique", "primary"';
    }

    #[\Override]
    public function compileForeignKeys($schema, $table): string
    {
        $this->assertMainSchema($schema);

        return 'select group_concat("from") as columns, \'main\' as foreign_schema, "table" as foreign_table, '
            .'group_concat("to") as foreign_columns, on_update, on_delete '
            .'from (select * from pragma_foreign_key_list('.$this->quoteString($table).') order by id desc, seq) '
            .'group by id, "table", on_update, on_delete';
    }

    private function assertMainSchema(?string $schema): void
    {
        if ($schema !== null && $schema !== 'main') {
            throw new InvalidArgumentException('Turso supports schema inspection only for the main database.');
        }
    }
}
