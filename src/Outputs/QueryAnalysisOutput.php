<?php

namespace AIQueryOptimizer\Outputs;

use NeuronAI\StructuredOutput\SchemaProperty;

class QueryAnalysisOutput
{
    /**
     * @var string[]
     */
    #[SchemaProperty(description: 'A list of JSON strings. Each string is a JSON object containing the query and its analysis.', required: true)]
    public array $analyses;
}
