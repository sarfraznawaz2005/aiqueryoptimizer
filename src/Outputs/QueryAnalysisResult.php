<?php

namespace AIQueryOptimizer\Outputs;

use NeuronAI\StructuredOutput\SchemaProperty;

class QueryAnalysisResult
{
    #[SchemaProperty(description: 'The original SQL query that was analyzed.', required: true)]
    public string $query;

    #[SchemaProperty(description: 'The detailed query_analysis and optimization suggestions for the query.', required: true)]
    public string $query_analysis;
}
