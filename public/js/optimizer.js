document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('ai-query-optimizer-btn');
    const modal = document.getElementById('ai-query-optimizer-modal');
    const closeBtn = document.getElementById('ai-query-optimizer-close');
    const countEl = document.getElementById('ai-query-optimizer-count');
    const resultsEl = document.getElementById('ai-query-optimizer-results');
    const toggleOptimizedCheckbox = document.getElementById('toggle-optimized');

    let queries = [];

    function initialize() {
        queries = window.initialQueries || []; // Populate from initial queries
        updateQueryCount();

        toggleOptimizedCheckbox.addEventListener('change', function () {
            if (this.checked) {
                modal.classList.remove('hide-optimized');
            } else {
                modal.classList.add('hide-optimized');
            }
        });
    }

    btn.addEventListener('click', function () {
        modal.style.display = 'block';
        resultsEl.innerHTML = '<div style="display: flex; align-items: center;"><div class="loader"></div><p>Analyzing Queries...</p></div>';

        fetch('/ai-query-optimizer/analyze', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ queries: queries })
        })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(errorData => {
                        throw new Error(errorData.error.message || 'An unknown error occurred.');
                    });
                }
                return response.json();
            })
            .then(data => {
                resultsEl.innerHTML = data.results;
            })
            .catch(error => {
                resultsEl.innerHTML = `<p style="color: red; font-weight: bold;">Error: ${error.message}</p>`;
                console.error('Error:', error);
            });
    });

    closeBtn.addEventListener('click', function () {
        modal.style.display = 'none';
    });

    window.addEventListener('click', function (event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });

    function updateQueryCount() {
        countEl.textContent = Math.min(queries.length, window.maxQueriesToSend);
    }

    function addQueries(newQueries) {
        const currentCount = queries.length;
        const maxAllowed = window.maxQueriesToSend;
        const remainingCapacity = maxAllowed - currentCount;

        if (remainingCapacity <= 0) {
            return; // Already at or over capacity
        }

        // Only add up to the remaining capacity
        const queriesToAdd = newQueries.slice(0, remainingCapacity);
        queries.push(...queriesToAdd);
        updateQueryCount();
    }

    // Intercept fetch
    const originalFetch = window.fetch;
    window.fetch = function (...args) {
        return originalFetch.apply(this, args).then(response => {
            if (response.headers.has('X-AI-Query-Optimizer-Queries')) {
                const ajaxQueries = JSON.parse(response.headers.get('X-AI-Query-Optimizer-Queries'));
                addQueries(ajaxQueries);
            }
            return response;
        });
    };

    // Intercept XMLHttpRequest
    const originalXhrOpen = XMLHttpRequest.prototype.open;
    XMLHttpRequest.prototype.open = function (...args) {
        this.addEventListener('load', function () {
            if (this.getResponseHeader('X-AI-Query-Optimizer-Queries')) {
                const ajaxQueries = JSON.parse(this.getResponseHeader('X-AI-Query-Optimizer-Queries'));
                addQueries(ajaxQueries);
            }
        });
        originalXhrOpen.apply(this, args);
    };

    initialize();
});
