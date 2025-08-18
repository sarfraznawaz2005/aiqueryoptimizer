<?php

namespace AIQueryOptimizer\Subscribers;

use Illuminate\Database\Events\QueryExecuted;
use AIQueryOptimizer\Collectors\QueryCollector;

class QuerySubscriber
{
    public function subscribe($events): void
    {
        $events->listen(
            QueryExecuted::class,
            [__CLASS__, 'handle']
        );
    }

    public function handle(QueryExecuted $event): void
    {
        app(QueryCollector::class)->addQuery($event);
    }
}
