<div>
    @foreach($results as $result)
        @include('ai-query-optimizer::partials.result-item', ['result' => $result])
    @endforeach
</div>
