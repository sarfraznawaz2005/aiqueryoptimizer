<?php

namespace AIQueryOptimizer\Tests;

use AIQueryOptimizer\Agents\QueryOptimizerAgent;
use AIQueryOptimizer\Outputs\QueryAnalysisOutput;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Mockery;

class OptimizerControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('ai-query-optimizer.enabled', true);
        Config::set('ai-query-optimizer.ai.provider', 'gemini');
        Config::set('ai-query-optimizer.ai.providers.gemini.key', 'test-key');
        Config::set('ai-query-optimizer.ai.providers.gemini.model', 'test-model');
    }

    public function test_analyze_endpoint_handles_batch_queries()
    {
        $agentMock = Mockery::mock('alias:' . QueryOptimizerAgent::class);
        $output = new QueryAnalysisOutput();
        $output->analyses = [
            json_encode(['query' => 'SELECT * FROM users', 'query_analysis' => 'Test Analysis'])
        ];
        $agentMock->shouldReceive('make')->andReturnSelf();
        $agentMock->shouldReceive('structured')->andReturn($output);

        $queries = [
            ['query' => 'SELECT * FROM users', 'query_explain_results' => '[]', 'query_time' => 10],
        ];

        $response = $this->postJson('/ai-query-optimizer/analyze', ['queries' => $queries]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['results']);
    }

    public function test_analyze_endpoint_uses_cache()
    {
        $query = 'SELECT * FROM users';
        $cacheKey = 'ai-query-optimizer-' . md5($query);
        Cache::put($cacheKey, ['analysis' => 'Cached Analysis'], 60);

        $queries = [
            ['query' => $query, 'query_explain_results' => '[]', 'query_time' => 10],
        ];

        $response = $this->postJson('/ai-query-optimizer/analyze', ['queries' => $queries]);

        $response->assertStatus(200);
        $this->assertStringContainsString('Cached Analysis', $response->content());
    }

    public function test_status_endpoint()
    {
        $query1 = 'SELECT * FROM users';
        $query2 = 'SELECT * FROM posts';
        $cacheKey1 = 'ai-query-optimizer-' . md5($query1);
        Cache::put($cacheKey1, ['analysis' => 'Cached Analysis'], 60);

        $queries = [
            ['query' => $query1, 'query_explain_results' => '[]', 'query_time' => 10],
            ['query' => $query2, 'query_explain_results' => '[]', 'query_time' => 10],
        ];

        $response = $this->postJson('/ai-query-optimizer/status', ['queries' => $queries]);

        $response->assertStatus(200);
        $response->assertJson([
            'cachedCount' => 1,
            'newCount' => 1,
        ]);
    }

    public function test_analyze_manual_endpoint()
    {
        $agentMock = Mockery::mock('alias:' . QueryOptimizerAgent::class);
        $output = new QueryAnalysisOutput();
        $output->analyses = [
            json_encode(['query' => 'SELECT * FROM users', 'query_analysis' => 'Test Analysis'])
        ];
        $agentMock->shouldReceive('make')->andReturnSelf();
        $agentMock->shouldReceive('structured')->andReturn($output);

        $response = $this->postJson('/ai-query-optimizer/analyze-manual', ['query' => 'SELECT * FROM users']);

        $response->assertStatus(200);
        $response->assertJsonStructure(['html']);
    }
}
