<link rel="stylesheet" href="{{ asset('vendor/ai-query-optimizer/css/optimizer.css') }}">

{{-- Main Button --}}
<div id="ai-query-optimizer-btn"
     style="position: fixed; bottom: 20px; right: 20px; z-index: 999999999; {{ config('ai-query-optimizer.ui.button_position') }}">
    <button
        style="background-color: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: bold; font-size: 14px;">
        (<span id="ai-query-optimizer-count">0</span>) AI Query Check
    </button>
</div>

{{-- Main Results Modal --}}
<div id="ai-query-optimizer-modal"
     style="display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
    <div
        style="background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 80%; border-radius: 5px;">
        <div
            style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 10px;">
            <div style="display: flex; align-items: center;">
                <h3 style="margin: 0;">AI Query Optimizer</h3>
                <button id="aqo-open-manual-modal-btn" class="aqo-header-btn">Manual Analysis</button>
            </div>
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

{{-- Manual Analysis Modal --}}
<div id="ai-query-optimizer-manual-modal"
     style="display: none; position: fixed; z-index: 10001; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
    <div
        style="background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 80%; border-radius: 5px;">
        <div
            style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 10px;">
            <h3 style="margin: 0;">Manual Query Analysis</h3>
            <span id="ai-query-optimizer-manual-close"
                  style="cursor: pointer; font-size: 28px; font-weight: bold;">&times;</span>
        </div>
        <div id="ai-query-optimizer-manual-analysis">
            <textarea id="aqo-manual-query-input" rows="4"
                      style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-family: monospace; font-size: 14px;" placeholder="Enter SQL query to analyze..."></textarea>
            <button id="aqo-manual-query-btn"
                    style="background-color: #28a745; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; font-weight: bold; margin-top: 10px;">
                Analyze Query
            </button>
            <div id="aqo-manual-query-result" style="margin-top: 15px;"></div>
        </div>
    </div>
</div>


<script>
    // Initialize the namespace and data required by the optimizer script.
    // This must be defined before the main optimizer.js script is loaded.
    window.AIQueryOptimizer = {
        initialQueries: @json(app(AIQueryOptimizer\Collectors\QueryCollector::class)->getQueries()),
        maxQueriesToSend: {{ config('ai-query-optimizer.query_processing.max_queries_to_send', 50) }}
    };
</script>
<script src="{{ asset('vendor/ai-query-optimizer/js/optimizer.js') }}"></script>