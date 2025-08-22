<?php

namespace AIQueryOptimizer\Tests;

use AIQueryOptimizer\Collectors\QueryCollector;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Config;

class QueryCollectorTest extends TestCase
{
    private QueryCollector $queryCollector;
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->queryCollector = new QueryCollector();
        $this->connection = $this->app['db']->connection();
    }

    public function test_it_only_collects_select_queries()
    {
        $event = new QueryExecuted('SELECT * FROM users', [], 10, $this->connection);
        $this->queryCollector->addQuery($event);
        $this->assertCount(1, $this->queryCollector->getQueries());

        $event = new QueryExecuted('UPDATE users SET name = ?', ['John'], 10, $this->connection);
        $this->queryCollector->addQuery($event);
        $this->assertCount(1, $this->queryCollector->getQueries());
    }

    public function test_it_replaces_bindings()
    {
        $event = new QueryExecuted('SELECT * FROM users WHERE id = ?', [1], 10, $this->connection);
        $this->queryCollector->addQuery($event);
        $queries = $this->queryCollector->getQueries();
        $this->assertStringContainsString("SELECT * FROM users WHERE id = 1", $queries[0]['query']);
    }

    public function test_it_ignores_queries_with_comment()
    {
        $event = new QueryExecuted('SELECT * FROM users /* AI_OPTIMIZER_IGNORE */', [], 10, $this->connection);
        $this->queryCollector->addQuery($event);
        $this->assertCount(0, $this->queryCollector->getQueries());
    }

    public function test_it_ignores_queries_matching_patterns()
    {
        Config::set('ai-query-optimizer.ignored_query_patterns', ['telescope']);
        $event = new QueryExecuted('SELECT * FROM telescope_entries', [], 10, $this->connection);
        $this->queryCollector->addQuery($event);
        $this->assertCount(0, $this->queryCollector->getQueries());
    }

    public function test_it_only_adds_unique_queries()
    {
        $event1 = new QueryExecuted('SELECT * FROM users', [], 10, $this->connection);
        $event2 = new QueryExecuted('SELECT * FROM users', [], 12, $this->connection);
        $this->queryCollector->addQuery($event1);
        $this->queryCollector->addQuery($event2);
        $this->assertCount(1, $this->queryCollector->getQueries());
    }
}
