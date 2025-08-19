<?php

namespace AIQueryOptimizer;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use AIQueryOptimizer\Collectors\QueryCollector;
use AIQueryOptimizer\Services\TableMetaDataService;
use AIQueryOptimizer\Subscribers\QuerySubscriber;

class AIQueryOptimizerServiceProvider extends ServiceProvider
{
    public function boot(Request $request): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/ai-query-optimizer.php' => config_path('ai-query-optimizer.php'),
            ], 'config');

            $this->publishes([
                __DIR__ . '/../public' => public_path('vendor/ai-query-optimizer'),
            ], 'public');
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'ai-query-optimizer');
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');

        // Check if the optimizer should be enabled
        if ($this->shouldBeEnabled($request)) {
            Event::subscribe(QuerySubscriber::class);
            Event::listen(RequestHandled::class, function (RequestHandled $event) {
                $request = $event->request;
                $response = $event->response;

                $queryCollector = app(QueryCollector::class);
                $queries = $queryCollector->getQueries();

                if (empty($queries)) {
                    return;
                }

                if ($request->ajax()) {
                    $response->headers->set('X-AI-Query-Optimizer-Queries', json_encode($queries, JSON_THROW_ON_ERROR));
                } else {
                    $content = $response->getContent();
                    $optimizer = view('ai-query-optimizer::optimizer')->render(); // Render once

                    $position = strripos($content, '</head>');

                    if (false !== $position) {
                        $content = substr($content, 0, $position) . $optimizer . substr($content, $position);
                    } else {
                        // Fallback if </head> not found (unlikely for HTML)
                        $content .= $optimizer;
                    }

                    $response->setContent($content);
                    $response->headers->remove('Content-Length'); // Update content length
                }
            });
        }
    }

    protected function shouldBeEnabled(Request $request): bool
    {
        if (!config('ai-query-optimizer.enabled') || !in_array(app()->environment(), config('ai-query-optimizer.allowed_environments'))) {
            return false;
        }

        if (Str::is(config('ai-query-optimizer.excluded_url_patterns', []), $request->path())) {
            return false;
        }

        $provider = config('ai-query-optimizer.ai.provider');

        if (empty($provider)) {
            return false;
        }

        $key = config("ai-query-optimizer.ai.providers.$provider.key");
        $model = config("ai-query-optimizer.ai.providers.$provider.model");

        if (empty($key) || empty($model)) {
            return false;
        }

        return true;
    }

    /**
     * Register the application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/ai-query-optimizer.php', 'ai-query-optimizer'
        );

        $this->app->singleton(QueryCollector::class);

        $this->app->singleton(TableMetaDataService::class, function () {
            $service = new TableMetaDataService();
            $service->boot();
            return $service;
        });
    }
}
