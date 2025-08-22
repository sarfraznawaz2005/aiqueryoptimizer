<?php

namespace AIQueryOptimizer\Services;

use Illuminate\Support\Facades\DB;

class TableMetaDataService
{
    protected array $tables = [];
    public array $tablesInfo = [];
    public array $indexes = [];

    protected array $excludedTables = [
        'migrations', 'password_resets', 'failed_jobs', 'personal_access_tokens',
        'telescope_entries', 'telescope_entries_tags', 'telescope_monitoring', 'cache',
        'cache_locks', 'jobs', 'job_batches', 'meter_entries', 'oauth_access_tokens',
        'oauth_auth_codes', 'oauth_clients', 'oauth_personal_access_clients',
        'oauth_refresh_tokens', 'password_reset_tokens', 'plogs', 'pulse_aggregates',
        'pulse_entries', 'pulse_values', 'sessions', 'sselogs', 'verifybackup', 'welcomes',
    ];

    public function boot(): void
    {
        $driver = DB::connection()->getDriverName();

        match ($driver) {
            'mysql' => $this->bootMySQL(),
            'sqlite' => $this->bootSQLite(),
            'pgsql' => $this->bootPostgreSQL(),
            'sqlsrv' => $this->bootSQLServer(),
            default => [],
        };
    }

    protected function bootMySQL(): void
    {
        $database = DB::connection()->getDatabaseName();
        $tablesMeta = DB::select(
            'SELECT t.TABLE_NAME, COALESCE(t.TABLE_ROWS, 0) AS table_rows, ' .
            '(SELECT GROUP_CONCAT(k.COLUMN_NAME ORDER BY k.ORDINAL_POSITION) ' .
            'FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS c ' .
            'LEFT JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE k ON k.CONSTRAINT_SCHEMA = c.CONSTRAINT_SCHEMA ' .
            'AND k.CONSTRAINT_NAME = c.CONSTRAINT_NAME AND k.TABLE_NAME = c.TABLE_NAME ' .
            'WHERE c.CONSTRAINT_TYPE = "PRIMARY KEY" AND c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.TABLE_NAME = t.TABLE_NAME) AS pk_cols ' .
            'FROM INFORMATION_SCHEMA.TABLES t WHERE t.TABLE_SCHEMA = ? ORDER BY t.TABLE_NAME',
            [$database]
        );

        $this->processTables($tablesMeta, 'TABLE_NAME', 'table_rows', 'pk_cols');

        foreach ($this->tables as $table) {
            $indexes = DB::select(
                'SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols ' .
                'FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ' .
                'GROUP BY INDEX_NAME, NON_UNIQUE',
                [$database, $table]
            );
            $this->processIndexes($indexes, $table, 'INDEX_NAME', 'cols');
        }
    }

    protected function bootSQLite(): void
    {
        $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
        $this->processTables($tables, 'name');

        foreach ($this->tables as $table) {
            $tableIndexes = DB::select("PRAGMA index_list('$table')");
            foreach ($tableIndexes as $index) {
                $indexInfo = DB::select("PRAGMA index_info('{$index->name}')");
                $cols = array_column($indexInfo, 'name');
                $this->indexes[] = sprintf('- INDEX `%s` on `%s` (%s)', $index->name, $table, implode(', ', $cols));
            }
        }
    }

    protected function bootPostgreSQL(): void
    {
        $tables = DB::select(
            "SELECT table_name, (xpath('/row/c/text()', query_to_xml(format('select count(*) as c from %I.%I', table_schema, table_name), false, true, '')))[1]::text::int AS table_rows " .
            "FROM information_schema.tables WHERE table_schema = current_schema()"
        );
        $this->processTables($tables, 'table_name', 'table_rows');

        foreach ($this->tables as $table) {
            $indexes = DB::select(
                'SELECT indexname as index_name, indexdef as cols FROM pg_indexes WHERE tablename = ?',
                [$table]
            );
            $this->processIndexes($indexes, $table, 'index_name', 'cols');
        }
    }

    protected function bootSQLServer(): void
    {
        $tables = DB::select(
            'SELECT t.name AS table_name, p.rows AS table_rows FROM sys.tables t ' .
            'INNER JOIN sys.indexes i ON t.object_id = i.object_id ' .
            'INNER JOIN sys.partitions p ON i.object_id = p.object_id AND i.index_id = p.index_id ' .
            'WHERE i.index_id <= 1 GROUP BY t.name, p.rows'
        );
        $this->processTables($tables, 'table_name', 'table_rows');

        foreach ($this->tables as $table) {
            $indexes = DB::select(
                'SELECT i.name as index_name, c.name as col_name FROM sys.indexes i ' .
                'INNER JOIN sys.index_columns ic ON i.object_id = ic.object_id AND i.index_id = ic.index_id ' .
                'INNER JOIN sys.columns c ON ic.object_id = c.object_id AND c.column_id = ic.column_id ' .
                'WHERE i.object_id = OBJECT_ID(?) ORDER BY i.name',
                [$table]
            );
            $this->processIndexes($indexes, $table, 'index_name', 'col_name');
        }
    }

    protected function processTables(array $tablesMeta, string $nameKey, string $rowsKey = null, string $pkKey = null): void
    {
        foreach ($tablesMeta as $meta) {
            $tableName = $meta->{$nameKey};
            if (in_array($tableName, $this->excludedTables, true)) {
                continue;
            }

            $rows = $rowsKey ? number_format($meta->{$rowsKey}) : 'N/A';
            $pk = $pkKey ? ($meta->{$pkKey} ?: 'None') : 'N/A';
            $this->tablesInfo[] = sprintf('- **%s**: %s rows, Primary Key: %s', $tableName, $rows, $pk);
            $this->tables[] = $tableName;
        }
    }

    protected function processIndexes(array $indexes, string $table, string $nameKey, string $colsKey): void
    {
        foreach ($indexes as $index) {
            $indexName = $index->{$nameKey};
            if ($indexName === 'PRIMARY') {
                continue;
            }
            $this->indexes[] = sprintf('- INDEX `%s` on `%s` (%s)', $indexName, $table, $index->{$colsKey});
        }
    }
}