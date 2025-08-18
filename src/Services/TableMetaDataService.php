<?php

namespace AIQueryOptimizer\Services;

use Illuminate\Support\Facades\DB;

class TableMetaDataService
{
    protected array $tables = [];
    public array $tablesInfo = [];
    public array $indexes = [];

    protected array $excludedTables = [
        'migrations',
        'password_resets',
        'failed_jobs',
        'personal_access_tokens',
        'telescope_entries',
        'telescope_entries_tags',
        'telescope_monitoring',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'meter_entries',
        'oauth_access_tokens',
        'oauth_auth_codes',
        'oauth_clients',
        'oauth_personal_access_clients',
        'oauth_refresh_tokens',
        'password_reset_tokens',
        'plogs',
        'pulse_aggregates',
        'pulse_entries',
        'pulse_values',
        'sessions',
        'sselogs',
        'verifybackup',
        'welcomes',
    ];

    public function boot(): void
    {
        $this->setTableData();
        $this->setIndexes();
    }

    protected function setTableData(): void
    {
        $database = DB::connection()->getDatabaseName();

        $tablesMeta = DB::select(
            'SELECT  t.TABLE_NAME,
                     COALESCE(t.TABLE_ROWS, 0)            AS table_rows,
                     COALESCE(
                         (SELECT GROUP_CONCAT(k.COLUMN_NAME ORDER BY k.ORDINAL_POSITION)
                            FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS c
                      LEFT JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE k
                              ON k.CONSTRAINT_SCHEMA = c.CONSTRAINT_SCHEMA
                             AND k.CONSTRAINT_NAME   = c.CONSTRAINT_NAME
                             AND k.TABLE_NAME        = c.TABLE_NAME
                           WHERE c.CONSTRAINT_TYPE = "PRIMARY KEY"
                             AND c.TABLE_SCHEMA     = t.TABLE_SCHEMA
                             AND c.TABLE_NAME       = t.TABLE_NAME
                         ),
                         NULL
                     )                         AS pk_cols
               FROM INFORMATION_SCHEMA.TABLES t
              WHERE t.TABLE_SCHEMA = ?
           ORDER BY t.TABLE_NAME',
            [$database]
        );

        $tableNames = [];
        $tablesInfo = [];

        foreach ($tablesMeta as $meta) {
            if (in_array($meta->TABLE_NAME, $this->excludedTables, true)) {
                continue;
            }

            $pk = $meta->pk_cols ?: 'None';
            $rows = number_format($meta->table_rows);
            $tablesInfo[] = sprintf(
                '- **%s**: %s rows, Primary Key: %s',
                $meta->TABLE_NAME,
                $rows,
                $pk
            );
            $tableNames[] = $meta->TABLE_NAME;
        }

        $this->tables = $tableNames;
        $this->tablesInfo = $tablesInfo;
    }

    protected function setIndexes(): void
    {
        $database = DB::connection()->getDatabaseName();
        $allIndexes = [];

        foreach ($this->tables as $table) {
            $indexes = DB::select(
                'SELECT INDEX_NAME,
                        GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols,
                        NON_UNIQUE
                   FROM INFORMATION_SCHEMA.STATISTICS
                  WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
               GROUP BY INDEX_NAME, NON_UNIQUE',
                [$database, $table]
            );

            foreach ($indexes as $index) {
                if ($index->INDEX_NAME === 'PRIMARY') {
                    continue;
                }

                $allIndexes[] = sprintf(
                    '- INDEX `%s` on `%s` (%s)',
                    $index->INDEX_NAME,
                    $table,
                    $index->cols
                );
            }
        }

        $this->indexes = $allIndexes;
    }
}

