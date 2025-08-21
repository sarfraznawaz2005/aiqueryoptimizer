<?php

namespace AIQueryOptimizer\Collectors;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class QueryCollector
{
    protected array $queries = [];

    public function addQuery(QueryExecuted $event): void
    {
        // Filter out non-SELECT queries
        if (!$this->isSelectQuery($event->sql)) {
            return;
        }

        $sql = $this->replaceBindings($event);

        // Allow developers to ignore specific queries
        if (str_contains($sql, '/* AI_OPTIMIZER_IGNORE */')) {
            return;
        }

        // Check against configured ignored patterns
        $ignoredPatterns = config('ai-query-optimizer.ignored_query_patterns', []);
        foreach ($ignoredPatterns as $pattern) {
            $patternNormalized = str_replace('`', '', $pattern);

            if (str_contains($sql, $pattern) || str_contains($sql, $patternNormalized)) {
                return; // Ignore this query
            }
        }

        // Check for uniqueness
        if (isset($this->queries[$sql])) {
            return;
        }

        try {
            $explainQuery = "EXPLAIN $sql";
            $result = DB::select($explainQuery);
            $explainResults = json_encode($result, JSON_PRETTY_PRINT);

            $this->queries[$sql] = [
                'query' => $sql,
                'query_explain_results' => $explainResults,
                'query_time' => $event->time,
                'query_analysis' => '',
                'connection' => $event->connectionName,
            ];
        } catch (Throwable $e) {
            Log::warning('Failed to EXPLAIN query:' . $e->getMessage(), ['query' => $sql]);
        }

    }

    public function getQueries(): array
    {
        return array_values($this->queries);
    }

    protected function isSelectQuery(string $sql): bool
    {
        // Trim whitespace and parentheses from the beginning of the query.
        $trimmedSql = ltrim($sql, " 	\n\r\0\x0B(");
        return str_starts_with(strtolower($trimmedSql), 'select');
    }

    protected function replaceBindings($event): string
    {
        /* @noinspection ALL */
        $sql = $event->sql;

        foreach ($this->formatBindings($event) as $key => $binding) {
            $regex = is_numeric($key)
                ? "/\?(?=(?:[^'\\']*'[^'\\']*')*[^'\\']*$)/"
                : "/:$key(?=(?:[^'\\']*'[^'\\']*')*[^'\\']*$)/";

            if ($binding === null) {
                $binding = 'null';
            } elseif (!is_int($binding) && !is_float($binding)) {
                $binding = $event->connection->getPdo()->quote($binding);
            }

            $sql = preg_replace($regex, $binding, $sql, 1);
        }

        return $sql;
    }

    protected function formatBindings($event): array
    {
        return $event->connection->prepareBindings($event->bindings);
    }
}


