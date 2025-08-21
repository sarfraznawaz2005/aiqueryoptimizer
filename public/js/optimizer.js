// Ensure the global namespace exists, preserving any data set by inline scripts.
window.AIQueryOptimizer = window.AIQueryOptimizer || {};

// Pass the namespace into a self-executing function to create a private scope.
(function (AQO) {

    // --- Private State ---
    let queries = [];

    // --- Private Methods ---
    function updateQueryCount() {
        const countEl = document.getElementById('ai-query-optimizer-count');
        const buttonEl = document.getElementById('ai-query-optimizer-btn')?.querySelector('button');

        if (countEl && buttonEl) {
            const queryCount = queries.length;
            const maxAllowed = AQO.maxQueriesToSend || 50;
            countEl.textContent = Math.min(queryCount, maxAllowed);
            buttonEl.disabled = (queryCount === 0);
        }
    }

    // --- Public Methods ---
    AQO.addQueries = function (newQueries) {
        if (!Array.isArray(newQueries)) return;

        const maxAllowed = AQO.maxQueriesToSend || 50;
        if (queries.length >= maxAllowed) {
            return; // Stop if we've hit capacity
        }

        const existingQuerySet = new Set(queries.map(q => q.query));
        const uniqueNewQueries = newQueries.filter(nq => nq && nq.query && !existingQuerySet.has(nq.query));

        const remainingCapacity = maxAllowed - queries.length;
        const queriesToAdd = uniqueNewQueries.slice(0, remainingCapacity);

        queries.push(...queriesToAdd);
        updateQueryCount();
    };

    // --- AJAX Interceptors (Execute Immediately) ---
    (function () {
        const originalFetch = window.fetch;
        window.fetch = function (...args) {
            if (args[0] && typeof args[0] === 'string' && args[0].includes('/ai-query-optimizer/')) {
                return originalFetch.apply(this, args);
            }

            return originalFetch.apply(this, args).then(response => {
                const clonedResponse = response.clone();
                if (clonedResponse.headers.has('X-AI-Query-Optimizer-Queries')) {
                    try {
                        const ajaxQueries = JSON.parse(clonedResponse.headers.get('X-AI-Query-Optimizer-Queries'));
                        AQO.addQueries(ajaxQueries); // Use public method
                    } catch (e) {
                        console.warn('AI Query Optimizer: Could not parse queries from fetch header.', e);
                    }
                }
                return response;
            });
        };

        const originalXhrOpen = XMLHttpRequest.prototype.open;
        XMLHttpRequest.prototype.open = function (...args) {
            this.addEventListener('load', function () {
                if (this.readyState === 4 && this.getResponseHeader('X-AI-Query-Optimizer-Queries')) {
                    try {
                        const ajaxQueries = JSON.parse(this.getResponseHeader('X-AI-Query-Optimizer-Queries'));
                        AQO.addQueries(ajaxQueries); // Use public method
                    } catch (e) {
                        console.warn('AI Query Optimizer: Could not parse queries from XHR header.', e);
                    }
                }
            });
            originalXhrOpen.apply(this, args);
        };
    })();

    // --- UI and Modal Logic (Execute on DOM Load) ---
    document.addEventListener('DOMContentLoaded', function () {
        // Element Selectors
        const btn = document.getElementById('ai-query-optimizer-btn');
        const modal = document.getElementById('ai-query-optimizer-modal');
        const closeBtn = document.getElementById('ai-query-optimizer-close');
        const resultsEl = document.getElementById('ai-query-optimizer-results');
        const toggleOptimizedCheckbox = document.getElementById('toggle-optimized');
        const manualQueryInput = document.getElementById('aqo-manual-query-input');
        const manualQueryBtn = document.getElementById('aqo-manual-query-btn');
        const manualQueryResultEl = document.getElementById('aqo-manual-query-result');
        const manualModal = document.getElementById('ai-query-optimizer-manual-modal');
        const openManualModalBtn = document.getElementById('aqo-open-manual-modal-btn');
        const closeManualModalBtn = document.getElementById('ai-query-optimizer-manual-close');

        // --- UI State Management ---
        function openMainModal() {
            manualModal.style.display = 'none';
            modal.style.display = 'block';
            btn.style.display = 'none';
        }

        function openManualModal() {
            modal.style.display = 'none';
            manualModal.style.display = 'block';
            btn.style.display = 'none';
        }

        function closeAllModals() {
            modal.style.display = 'none';
            manualModal.style.display = 'none';
            btn.style.display = 'block';
        }

        // --- Event Handlers ---
        function handleCheckAgain(button) {
            const rawQuery = button.dataset.query;
            const resultContainer = button.closest('.aqo-query-result-container');
            const analysisContent = resultContainer.querySelector('.analysis-content');
            const queryData = queries.find(q => q.query === rawQuery);

            if (!queryData) {
                analysisContent.innerHTML = `<p style="color: red; font-weight: bold;">Error: Could not find original query data.</p>`;
                return;
            }

            analysisContent.innerHTML = '<div class="aqo-statusText"><div class="aqo-loader"></div><p>Re-analyzing...</p></div>';
            button.disabled = true; // Disable the button here

            fetch('/ai-query-optimizer/analyze', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({queries: queryData, bypass_cache: true})
            })
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(err => { throw new Error(err.error.message || 'Unknown error'); });
                    }
                    return response.json();
                })
                .then(data => {
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = data.html;
                    resultContainer.parentNode.replaceChild(tempDiv.firstChild, resultContainer);
                })
                .catch(error => {
                    analysisContent.innerHTML = `<p style="color: red; font-weight: bold;">Error: ${error.message}</p>`;
                })
                .finally(() => {
                    button.disabled = false; // Re-enable the button in the finally block
                });
        }

        function handleManualQueryAnalysis() {
            const query = manualQueryInput.value.trim();
            if (!query) {
                manualQueryResultEl.innerHTML = `<p style="color: orange; font-weight: bold;">Please enter a query to analyze.</p>`;
                return;
            }

            manualQueryResultEl.innerHTML = `<div class="aqo-statusText"><div class="aqo-loader"></div><p>Analyzing manual query...</p></div>`;
            manualQueryBtn.disabled = true; // Disable the button here

            fetch('/ai-query-optimizer/analyze-manual', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({ query: query })
            })
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(err => { throw new Error(err.error.message || 'Unknown error during manual analysis.'); });
                    }
                    return response.json();
                })
                .then(data => { manualQueryResultEl.innerHTML = data.html; })
                .catch(error => { manualQueryResultEl.innerHTML = `<p style="color: red; font-weight: bold;">Error: ${error.message}</p>`; })
                .finally(() => { manualQueryBtn.disabled = false; }); // Re-enable the button in the finally block
        }

        // --- Initial Load ---
        AQO.addQueries(AQO.initialQueries || []);

        // --- Attach Event Listeners ---
        if (btn) btn.addEventListener('click', function () {
            openMainModal();
            resultsEl.innerHTML = '<div class="aqo-statusText"><div class="aqo-loader"></div><p>Preparing analysis...</p></div>';

            fetch('/ai-query-optimizer/status', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({queries: queries})
            })
                .then(response => {
                    if (!response.ok) { throw new Error('Could not retrieve query status.'); }
                    return response.json();
                })
                .then(statusData => {
                    let loadingText = `<div class="aqo-statusText"><div class="aqo-loader"></div><p>Analyzing Queries...<br>`;
                    if (statusData.cachedCount > 0) { loadingText += `Found ${statusData.cachedCount} cached results. `; }
                    if (statusData.newCount > 0) { loadingText += `Sending ${statusData.newCount} new queries to AI.`; }
                    loadingText += `</p></div>`;
                    resultsEl.innerHTML = loadingText;

                    return fetch('/ai-query-optimizer/analyze', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                        },
                        body: JSON.stringify({queries: queries})
                    });
                })
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(errorData => { throw new Error(errorData.error.message || 'An unknown error occurred during analysis.'); });
                    }
                    return response.json();
                })
                .then(data => { resultsEl.innerHTML = data.results; })
                .catch(error => {
                    resultsEl.innerHTML = `<p style="color: red; font-weight: bold;">Error: ${error.message}</p>`;
                    console.error('Error:', error);
                });
        });

        if (closeBtn) closeBtn.addEventListener('click', closeAllModals);
        if (openManualModalBtn) openManualModalBtn.addEventListener('click', openManualModal);
        if (closeManualModalBtn) closeManualModalBtn.addEventListener('click', closeAllModals);
        if (manualQueryBtn) manualQueryBtn.addEventListener('click', handleManualQueryAnalysis);

        if (toggleOptimizedCheckbox) {
            toggleOptimizedCheckbox.addEventListener('change', function () {
                modal.classList.toggle('aqo-hide-optimized', !this.checked);
            });
        }

        resultsEl.addEventListener('click', function (event) {
            const checkAgainButton = event.target.closest('.aqo-check-again-btn');
            if (checkAgainButton) {
                event.stopPropagation();
                handleCheckAgain(checkAgainButton);
                return;
            }

            const header = event.target.closest('.aqo-collapsible-header');
            if (header) {
                const container = header.closest('.aqo-collapsible');
                if (container) container.classList.toggle('aqo-collapsed');
            }
        });

        window.addEventListener('click', function (event) {
            if (event.target === modal || event.target === manualModal) {
                closeAllModals();
            }
        });
    });

})(window.AIQueryOptimizer);
