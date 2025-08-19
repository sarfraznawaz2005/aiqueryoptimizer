<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Query Optimizer Master Switch
    |--------------------------------------------------------------------------
    |
    | This option allows you to easily enable or disable the entire package.
    |
    */
    'enabled' => env('AI_QUERY_OPTIMIZER_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Allowed Environments
    |--------------------------------------------------------------------------
    |
    | The package will only run in these specified environments. This is a
    | safeguard to prevent it from ever running on 'production'.
    |
    */
    'allowed_environments' => ['local', 'development', 'staging'],

    /*
    |--------------------------------------------------------------------------
    | Excluded URL Patterns
    |--------------------------------------------------------------------------
    |
    | The query analyzer will not activate on routes whose paths match these
    | patterns. Uses standard Laravel `Str::is()` for matching.
    |
    | Example: ['admin/*', 'telescope*']
    |
    */
    'excluded_url_patterns' => [
		'*ai-query-optimizer*',
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the AI provider and model to be used for analysis. This uses
    | the inspector-apm/neuron-ai package structure.
    |
    */
    'ai' => [
        'provider' => env('AI_PROVIDER', 'gemini'), // 'gemini', 'openai'

        'providers' => [
            'gemini' => [
                'key' => env('GEMINI_API_KEY'),
                'model' => env('GEMINI_MODEL'),
            ],
            'openai' => [
                'key' => env('OPENAI_API_KEY'),
                'model' => env('OPENAI_MODEL'),
                'organization' => env('OPENAI_ORGANIZATION'),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Configure caching for AI analysis to reduce API costs and speed up
    | repeated analyses of the same query.
    |
    */
    'cache' => [
        'enabled' => true,
        'duration_in_minutes' => 60 * 24, // Cache for one day
    ],

    /*
    |--------------------------------------------------------------------------
    | Frontend Customization
    |--------------------------------------------------------------------------
    |
    | Control the appearance of the frontend components.
    |
    */
    'ui' => [
        'button_position' => 'bottom-right', // 'bottom-right', 'bottom-left', 'top-right', 'top-left'
    ],

    /*
    |--------------------------------------------------------------------------
    | Query Processing
    |--------------------------------------------------------------------------
    |
    | Configure how queries are processed before sending to the AI.
    |
    */
    'query_processing' => [
        'max_queries_to_send' => 50, // Maximum number of queries to send to AI
    ],
];
