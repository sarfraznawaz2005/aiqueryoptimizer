<div
    class="query-result aqo-query-result-container aqo-collapsible {{ str_contains(strtolower($result['analysis']), 'already optimized') ? 'aqo-optimized-query' : '' }}"
    style="padding: 15px; margin-bottom: 15px; border-radius: 5px; {{str_contains(strtolower($result['analysis']), 'already optimized') ? ' font-size: 90%; border: 1px solid #28a745; background-color: #f3fff5' : 'border: 1px solid #ddd; background-color: #f4f4f4'}};">

    <div class="aqo-collapsible-header">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <div>
                {{--
                @if($result['cached'])
                    <span
                        style="background-color: #28a745; color: white; padding: 3px 8px; border-radius: 10px; font-size: 12px; font-weight: bold;">Cached</span>
                @else
                    <span
                        style="background-color: #007bff; color: white; padding: 3px 8px; border-radius: 10px; font-size: 12px; font-weight: bold;">New!</span>
                @endif
                --}}
                @if (!isset($result['is_manual']) || !$result['is_manual'])
                    @if(isset($result['time']))
                        <span
                            style="background-color: #17a2b8; color: white; padding: 3px 8px; border-radius: 10px; font-size: 12px; font-weight: bold; margin-left: 5px;">Time: {{ round($result['time'], 2) }}ms</span>
                    @endif
                    @if($result['count'] > 1)
                        <span
                            style="background-color: #ffc107; color: #111; padding: 3px 8px; border-radius: 10px; font-size: 12px; font-weight: bold; margin-left: 5px;">Executed {{ $result['count'] }} times</span>
                    @endif
                @endif
            </div>
            @if (!isset($result['is_manual']) || !$result['is_manual'])
                <button class="aqo-check-again-btn" data-query="{{ $result['raw_query'] }}"
                        style="background: #007bff; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer;">
                    Check Again
                </button>
            @endif
        </div>

        @if (!isset($result['is_manual']) || !$result['is_manual'])
            <pre><code>{!! $result['query'] !!}</code></pre>
        @endif
    </div>

    <div class="analysis-content">
        @if(str_contains(strtolower($result['analysis']), 'already optimized'))
            <div
                style="background-color: #d4edda; font-weight: bold; color: #155724; padding: 10px; border-radius: 5px; border: 1px solid #c3e6cb;">
                Already Optimized
            </div>
        @else
            <div>{!! $result['analysis'] !!}</div>
        @endif
    </div>
</div>
