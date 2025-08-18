<div>
    @foreach($results as $result)
        <div class="query-result {{ str_contains(strtolower($result['analysis']), 'already optimized') ? 'optimized-query' : '' }}"
             style="margin-bottom: 20px; padding: 10px; border-radius: 5px; {{str_contains(strtolower($result['analysis']), 'already optimized') ? 'border: 1px solid #28a745; background-color: #f3fff5' : 'border: 1px solid #eee; background-color: #f4f4f4'}};">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                @if($result['count'] > 1)
                    <span style="background-color: #ffc107; color: #111; padding: 2px 5px; border-radius: 3px;">Executed {{ $result['count'] }} times</span>
                @endif
            </div>
            <pre><code>{!! $result['query'] !!}</code></pre>

            @if(str_contains(strtolower($result['analysis']), 'already optimized'))
                <div style="background-color: #28a745; color: white; padding: 10px; border-radius: 5px;">
                    Already Optimized
                </div>
            @else
                <div>{!! $result['analysis'] !!}</div>
            @endif
        </div>
    @endforeach
</div>
