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
    /**
     * @throws Throwable
     */
    public function analyze(Request $request)
    {
        try {

            $queries = $request->get('queries', []);
            //dd($queries);

            $results = [];
            $agent = QueryOptimizerAgent::make();
            $highlighter = new Highlighter();
            $parsedown = new ParsedownExtra();

            $queriesForAI = [];
            $queryCounts = [];

            // Calculate counts and prepare for AI
            foreach ($queries as $queryData) {
                $sql = $queryData['query'];
                if (!isset($queryCounts[$sql])) {
                    $queryCounts[$sql] = 0;
                }
                $queryCounts[$sql]++;

                // Cache check
                $cacheKey = 'ai-query-optimizer-' . md5($sql);
                if (Cache::has($cacheKey)) {
                    $cachedResult = Cache::get($cacheKey);
                    $analysis = $cachedResult['analysis'];
                    $results[] = [
                        'query' => $highlighter->parse($sql, 'sql'),
                        'analysis' => (trim($analysis) === 'Already Optimized') ? '<p style="color: green; font-weight: bold;">Already Optimized</p>' : $parsedown->text($analysis),
                        'count' => $queryCounts[$sql],
                    ];
                } else {
                    // Only add unique queries to be sent to the AI
                    if (!isset($queriesForAI[$sql])) {
                        $queriesForAI[$sql] = $queryData;
                    }
                }
            }

            $queriesForAI = array_values($queriesForAI);

            if (empty($queriesForAI)) {
                return response()->json([
                    'results' => view('ai-query-optimizer::results', ['results' => $results])->render(),
                ]);
            }

            // Apply query processing settings
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
            //dd($agentResponse);

            // Process AI response
            foreach ($agentResponse->analyses as $analysisJsonString) {
                $analysisResult = json_decode($analysisJsonString, true);

                if ($analysisResult === null) {
                    Log::warning('AI returned a malformed JSON string in the analysis array:', ['json' => $analysisJsonString]);
                    continue;
                }

                $sql = $analysisResult['query'] ?? '';
                $analysis = $analysisResult['query_analysis'] ?? '';

                if (!$sql || !$analysis) {
                    Log::warning('AI returned a malformed analysis result inside the JSON string:', (array)$analysisResult);
                    continue;
                }

                // Store in cache
                $cacheKey = 'ai-query-optimizer-' . md5($sql);
                Cache::put($cacheKey, ['analysis' => $analysis], config('ai-query-optimizer.cache.duration_in_minutes', 60 * 24));

                $displayAnalysis = (trim($analysis) === 'Already Optimized') ? '<p style="color: green; font-weight: bold;">Already Optimized</p>' : $parsedown->text($analysis);

                $results[] = [
                    'query' => $highlighter->parse($sql, 'sql'),
                    'analysis' => $displayAnalysis,
                    'count' => $queryCounts[$sql] ?? 1,
                ];
            }

            return response()->json([
                'results' => view('ai-query-optimizer::results', ['results' => $results])->render(),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'error' => [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                ],
            ], 500);
        }
    }

}

