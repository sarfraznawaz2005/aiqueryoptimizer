<?php

namespace AIQueryOptimizer\Agents;

use InvalidArgumentException;
use NeuronAI\Agent;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Gemini\Gemini;
use NeuronAI\Providers\OpenAI\OpenAI;
use NeuronAI\SystemPrompt;
use AIQueryOptimizer\Services\TableMetaDataService;

class QueryOptimizerAgent extends Agent
{
    protected function provider(): AIProviderInterface
    {
        $providerName = config('ai-query-optimizer.ai.provider');
        $providerConfig = config('ai-query-optimizer.ai.providers.' . $providerName);

        return match ($providerName) {
            'gemini' => new Gemini(
                key: $providerConfig['key'],
                model: $providerConfig['model'],
                parameters: [
                    'safetySettings' => [
                        [
                            'category' => 'HARM_CATEGORY_HARASSMENT',
                            'threshold' => 'BLOCK_NONE',
                        ],
                        [
                            'category' => 'HARM_CATEGORY_HATE_SPEECH',
                            'threshold' => 'BLOCK_NONE',
                        ],
                        [
                            'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                            'threshold' => 'BLOCK_NONE',
                        ],
                        [
                            'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                            'threshold' => 'BLOCK_NONE',
                        ],
                    ],
                    'generationConfig' => [
                        'maxOutputTokens' => 16384,
                        'temperature' => 0,
                        'thinkingConfig' => [
                            "thinkingBudget" => -1
                            # Thinking off:
                            # "thinkingBudget": 0
                            # Turn on dynamic thinking:
                            # "thinkingBudget": -1
                        ]
                    ]
                ]
            ),
            'openai' => new OpenAI(
                key: $providerConfig['key'],
                model: $providerConfig['model'],
            ),
            default => throw new InvalidArgumentException('Unsupported AI provider: ' . $providerName),
        };
    }

    public function instructions(): string
    {
        $tableMeta = app(TableMetaDataService::class);

        $tablesInfo = $tableMeta->tablesInfo;
        $indexesInfo = $tableMeta->indexes;

        $background = [
            'You are an expert database administrator and SQL query optimizer.',
            'Your goal is to analyze SQL queries and provide detailed, actionable suggestions for improvement.',
            'You will be given a JSON array of query data objects.',
            "\n## Tables Overview:\n\n" . implode("\n", $tablesInfo),
            "\n## Applied Indexes:\n\n" . implode("\n", $indexesInfo),
        ];

        return new SystemPrompt(
            background: $background,
            steps: [
                'For each query object in the input array, you must analyze its performance characteristics based on the `query`, `query_explain_results`, and `query_time` keys.',
                'Based on your analysis, provide a detailed explanation of any potential performance issues.',
                'Suggest concrete improvements, such as adding/removing indexes (via ALTER queries), rewriting the query, or changing the table structure. Use the provided table and index information to avoid suggesting already existing indexes.',
                'Present your analysis in a clear, easy-to-understand format using Markdown.',
                'The analysis for each query must contain two sections with Markdown headings: "#### Query Analysis" and "#### Suggestions".',
                'If a query is already well-optimized, simply state: `Already Optimized` and nothing else.',
            ],
            output: [
                'You MUST return a JSON object with a single key: "analyses".',
                'The value of "analyses" MUST be an array of JSON strings.',
                'Each string in the array MUST be a valid JSON object containing two keys: "query" and "query_analysis".',
                'Example for a single element in the array: "{\"query\": \"SELECT * FROM users\", \"query_analysis\": \"#### Query Analysis\nThis is the analysis.\n\n#### Suggestions\nList of suggestions.\"}"'
            ]
        );
    }
}
