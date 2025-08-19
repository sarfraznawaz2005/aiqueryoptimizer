let queries = []; // Moved to global scope for accessibility

function addQueries(newQueries) {
    const maxAllowed = window.maxQueriesToSend;

    // Create a set of existing query strings for quick lookup
    const existingQueryStrings = new Set(queries.map(q => q.query));

    for (const newQuery of newQueries) {
        if (queries.length >= maxAllowed) {
            break; // Stop if we've hit capacity
        }
        if (!existingQueryStrings.has(newQuery.query)) {
            queries.push(newQuery);
            existingQueryStrings.add(newQuery.query); // Add to set as well
        }
    }
    updateQueryCount();
}

function updateQueryCount() { // Moved to global scope
    const countEl = document.getElementById('ai-query-optimizer-count'); // Get element here
    if (countEl) { // Ensure element exists before updating
        countEl.textContent = Math.min(queries.length, window.maxQueriesToSend);
    }
}


// --- Intercept fetch & XMLHttpRequest to capture AJAX queries ---
(function () {
    // --- Fetch Interceptor ---
    const originalFetch = window.fetch;
    if (originalFetch) {
        window.fetch = function (...args) {
            return originalFetch.apply(this, args).then(response => {
                if (response && response.headers && response.headers.has('X-AI-Query-Optimizer-Queries')) {
                    try {
                        const ajaxQueries = JSON.parse(response.headers.get('X-AI-Query-Optimizer-Queries'));
                        addQueries(ajaxQueries);
                    } catch (e) {
                        // Fail silently if parsing fails
                    }
                }
                return response;
            });
        };
    }

    // --- XMLHttpRequest Interceptor ---
    const OriginalXMLHttpRequest = window.XMLHttpRequest;

    window.XMLHttpRequest = function () {
        const xhr = new OriginalXMLHttpRequest();

        // Apply our event listener to this new instance
        xhr.addEventListener('load', function () {
            if (xhr.readyState === 4) {
                const header = xhr.getResponseHeader('X-AI-Query-Optimizer-Queries');
                if (header) {
                    try {
                        const ajaxQueries = JSON.parse(header);
                        addQueries(ajaxQueries);
                    } catch (e) { /* silent fail */
                    }
                }
            }
        });
        return xhr;
    };
})(); // End of self-invoking function for XHR patch


// --- Main DOMContentLoaded logic ---
document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('ai-query-optimizer-btn');
    const modal = document.getElementById('ai-query-optimizer-modal');
    const closeBtn = document.getElementById('ai-query-optimizer-close');
    const resultsEl = document.getElementById('ai-query-optimizer-results');
    const toggleOptimizedCheckbox = document.getElementById('toggle-optimized');

    // queries array is now global
    // updateQueryCount is now global

    function initialize() {
        queries = window.initialQueries || [];
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
        btn.style.display = 'none'; // Hide the button when modal opens
        resultsEl.innerHTML = '<div style="display: flex; align-items: center;"><div class="loader"></div><p>Preparing analysis...</p></div>';

        fetch('/ai-query-optimizer/status', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({queries: queries})
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Could not retrieve query status.');
                }
                return response.json();
            })
            .then(statusData => {
                let loadingText = `<div style="display: flex; align-items: center;"><div class="loader"></div><p>Analyzing Queries...<br><small style="font-weight: normal;">`;
                if (statusData.cachedCount > 0) {
                    loadingText += `Found ${statusData.cachedCount} cached results. `;
                }
                if (statusData.newCount > 0) {
                    loadingText += `Sending ${statusData.newCount} new queries to AI.`;
                }
                loadingText += `</small></p></div>`;
                resultsEl.innerHTML = loadingText;

                return fetch('/ai-query-optimizer/analyze', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({queries: queries})
                });
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(errorData => {
                        throw new Error(errorData.error.message || 'An unknown error occurred during analysis.');
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
            btn.style.display = 'block'; // Show the button when modal closes
        });

        window.addEventListener('click', function (event) {
            if (event.target === modal) {
                modal.style.display = 'none';
                btn.style.display = 'block'; // Show the button when modal closes
            }
        });

        initialize();
    });