<?php

namespace AIQueryOptimizer\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use NeuronAI\Chat\Messages\UserMessage;
use AIQueryOptimizer\Agents\QueryOptimizerAgent;
use AIQueryOptimizer\Outputs\QueryAnalysisOutput;
use ParsedownExtra;
use Tempest\Highlight\Highlighter;
use Throwable;

class OptimizerController extends Controller
{
	private Highlighter $highlighter;
    private ParsedownExtra $parsedown;

    public function __construct()
    {
        $this->highlighter = new Highlighter();
        $this->parsedown = new ParsedownExtra();
    }

    /**
     * @throws Throwable
     */
    public function analyze(Request $request)
    {
        try {
            $queries = $request->get('queries', []);
            $bypassCache = $request->get('bypass_cache', false);
            $isSingleQuery = isset($queries['query']);

            if ($isSingleQuery) {
                return $this->analyzeSingleQuery($queries, $bypassCache);
            }

            return $this->analyzeBatch($queries);

        } catch (Throwable $e) {
            Log::error('AI Query Optimizer Error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'error' => [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                ],
            ], 500);
        }
    }

    /**
     * @throws Throwable
     */
    private function analyzeBatch(array $queries)
    {
        $results = [];
        $queriesForAI = [];
        $queryCounts = [];

        // First pass: count queries and check cache
        foreach ($queries as $queryData) {
            $sql = $queryData['query'];
            $queryCounts[$sql] = ($queryCounts[$sql] ?? 0) + 1;

            // Only process unique queries for cache check and AI prep
            if (($queryCounts[$sql] ?? 0) > 1) {
                continue;
            }

            $cacheKey = 'ai-query-optimizer-' . md5($sql);
            if (Cache::has($cacheKey)) {
                $cachedResult = Cache::get($cacheKey);
                $analysis = $cachedResult['analysis'];
                $results[$sql] = [
                    'query' => $this->highlighter->parse($sql, 'sql'),
                    'raw_query' => $sql,
                    'analysis' => (trim(strtolower($analysis)) === 'already optimized') ? '<p style="color: green; font-weight: bold;">Already Optimized</p>' : $this->parsedown->text($analysis),
                    'count' => 0, // Will be updated later
                    'cached' => true,
                    'time' => $queryData['query_time'],
                ];
            } else {
                if (!isset($queriesForAI[$sql])) {
                    $queriesForAI[$sql] = $queryData;
                }
            }
        }

        // Process queries that need AI analysis
        if (!empty($queriesForAI)) {
            $this->processWithAI(array_values($queriesForAI), $results);
        }

        // Final pass: update counts and structure the final results array
        $finalResults = array_map(function ($sql, $result) use ($queryCounts) {
            $result['count'] = $queryCounts[$sql];
            return $result;
        }, array_keys($results), array_values($results));


        return response()->json([
            'results' => view('ai-query-optimizer::results', ['results' => $finalResults])->render(),
        ]);
    }

    /**
     * @throws Throwable
     */
    private function analyzeSingleQuery(array $queryData, bool $bypassCache)
    {
        $sql = $queryData['query'];
        $cacheKey = 'ai-query-optimizer-' . md5($sql);

        if (!$bypassCache && Cache::has($cacheKey)) {
            // This case should ideally not be hit if the button is used correctly, but as a fallback.
            $cachedResult = Cache::get($cacheKey);
            $analysis = $cachedResult['analysis'];
            $result = [
                'query' => $this->highlighter->parse($sql, 'sql'),
                'raw_query' => $sql,
                'analysis' => (trim(strtolower($analysis)) === 'already optimized') ? '<p style="color: green; font-weight: bold;">Already Optimized</p>' : $this->parsedown->text($analysis),
                'count' => 1,
                'cached' => true,
                'time' => $queryData['query_time'],
            ];
        } else {
            $results = [];
            $this->processWithAI([$queryData], $results);
            $result = $results[$sql];
            $result['count'] = 1; // Count is always 1 for a single check
        }

        return response()->json([
            'html' => view('ai-query-optimizer::partials.result-item', ['result' => $result])->render(),
        ]);
    }

    /**
     * @throws ReflectionException
     * @throws Throwable
     * @throws AgentException
     */
    private function processWithAI(array $queriesForAI, array &$results): void
    {
        if (empty($queriesForAI)) {
            return;
        }

        $agent = QueryOptimizerAgent::make();
        $queryTimeMap = array_column($queriesForAI, 'query_time', 'query');

        $maxQueriesToSend = config('ai-query-optimizer.query_processing.max_queries_to_send');
        if ($maxQueriesToSend && count($queriesForAI) > $maxQueriesToSend) {
            $queriesForAI = array_slice($queriesForAI, 0, $maxQueriesToSend);
        }

        $queriesJson = json_encode($queriesForAI, JSON_PRETTY_PRINT);

        /** @var QueryAnalysisOutput $agentResponse */
        $agentResponse = $agent->structured(
            new UserMessage($queriesJson),
            QueryAnalysisOutput::class,
            3
        );

        foreach ($agentResponse->analyses as $analysisJsonString) {
            $analysisResult = json_decode($analysisJsonString, true);
            if ($analysisResult === null) {
                Log::warning('AI returned a malformed JSON string', ['json' => $analysisJsonString]);
                continue;
            }

            $sql = $analysisResult['query'] ?? '';
            $analysis = $analysisResult['query_analysis'] ?? '';
            if (!$sql || !$analysis) {
                Log::warning('AI returned malformed analysis', (array)$analysisResult);
                continue;
            }

            $cacheKey = 'ai-query-optimizer-' . md5($sql);
            Cache::put($cacheKey, ['analysis' => $analysis], config('ai-query-optimizer.cache.duration_in_minutes', 60 * 24));

            $results[$sql] = [
                'query' => $this->highlighter->parse($sql, 'sql'),
                'raw_query' => $sql,
                'analysis' => (trim(strtolower($analysis)) === 'already optimized') ? '<p style="color: green; font-weight: bold;">Already Optimized</p>' : $this->parsedown->text($analysis),
                'cached' => false, // It's new from AI
                'time' => $queryTimeMap[$sql] ?? 0,
            ];
        }
    }

    public function status(Request $request)
    {
        try {
            $queries = $request->get('queries', []);
            $cachedCount = 0;
            $uniqueQueries = [];

            foreach ($queries as $queryData) {
                $sql = $queryData['query'];
                // Avoid processing the same SQL query multiple times from the input
                if (isset($uniqueQueries[$sql])) {
                    continue;
                }
                $uniqueQueries[$sql] = true;

                $cacheKey = 'ai-query-optimizer-' . md5($sql);
                if (Cache::has($cacheKey)) {
                    $cachedCount++;
                }
            }

            $totalUniqueQueries = count($uniqueQueries);
            $newCount = $totalUniqueQueries - $cachedCount;

            return response()->json([
                'cachedCount' => $cachedCount,
                'newCount' => $newCount,
            ]);

        } catch (Throwable) {
            return response()->json(['error' => 'Failed to get status'], 500);
        }
    }

    public function analyzeManual(Request $request)
    {
        try {
            $sql = $request->get('query');

            if (empty($sql)) {
                throw new InvalidArgumentException('Query cannot be empty.');
            }

            // Basic validation to only allow SELECT statements for safety
            if (!str_starts_with(strtolower(ltrim($sql)), 'select')) {
                throw new InvalidArgumentException('Only SELECT queries can be analyzed manually.');
            }

            $explainResults = DB::select("EXPLAIN " . $sql);

            $queryData = [
                'query' => $sql,
                'query_explain_results' => json_encode($explainResults, JSON_PRETTY_PRINT),
                'query_time' => 0, // No execution time for manual queries
                'connection' => config('database.default'),
            ];

            $results = [];
            $this->processWithAI([$queryData], $results);

            if (empty($results[$sql])) {
                throw new RuntimeException('Failed to get analysis for the query.');
            }

            $result = $results[$sql];
            $result['count'] = 1;
            $result['time'] = 0; // Explicitly set time to 0 for the view
            $result['is_manual'] = true;

            return response()->json([
                'html' => view('ai-query-optimizer::partials.result-item', ['result' => $result])->render(),
            ]);

        } catch (Throwable $e) {
            Log::error('AI Query Optimizer Manual Analysis Error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'error' => [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                ],
            ], 500);
        }
    }    
}

