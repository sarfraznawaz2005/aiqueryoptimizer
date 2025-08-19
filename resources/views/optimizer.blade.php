<link rel="stylesheet" href="{{ asset('vendor/ai-query-optimizer/css/optimizer.css') }}">

<div id="ai-query-optimizer-btn"
     style="position: fixed; bottom: 20px; right: 20px; z-index: 999999999; {{ config('ai-query-optimizer.ui.button_position') }}">
    <button
        style="background-color: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: bold; font-size: 14px;">
        (<span id="ai-query-optimizer-count">0</span>) AI Query Check
    </button>
</div>

<div id="ai-query-optimizer-modal"
     style="display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
    <div
        style="background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 80%; border-radius: 5px;">
        <div
            style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 10px;">
            <h3 style="margin: 0;">AI Query Optimizer</h3>
            <div>
                <label for="toggle-optimized" style="font-weight: normal; margin-right: 10px; font-size: 14px;">
                    <input type="checkbox" id="toggle-optimized" checked> Show Optimized
                </label>
                <span id="ai-query-optimizer-close"
                      style="cursor: pointer; font-size: 28px; font-weight: bold;">&times;</span>
            </div>
        </div>
        <div id="ai-query-optimizer-results"></div>
    </div>
</div>


<script>
    window.initialQueries = @json(app(AIQueryOptimizer\Collectors\QueryCollector::class)->getQueries());
    window.maxQueriesToSend = {{ config('ai-query-optimizer.query_processing.max_queries_to_send') }};
</script>
<script src="{{ asset('vendor/ai-query-optimizer/js/optimizer.js') }}"></script>
