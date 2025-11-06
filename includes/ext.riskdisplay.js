mw.loader.using(['ext.riskutils', 'ext.dropdown', 'ext.riskparameter', 'oojs-ui'], function () {
    'use strict';

    function matchPlaceholders(input) {
        // Regex:
        // \{         - Literal {
        // ([a-zA-Z0-9_]+) - Capture group 1: letters, numbers, underscore
        // \}         - Literal }
        const regex = /\{([a-zA-Z0-9_]+)\}/g;

        // Use matchAll to get an iterator of all matches
        const matches = input.matchAll(regex);

        const placeholders = new Set();
        for (const match of matches) {
            // check for {{ and }} to avoid matching templates
            const doubleFirst = (match.index > 0) && (input[match.index - 1] == '{');
            const doubleLast = (match.index + match[0].length < input.length) && // Check length before indexing
                (input[match.index + match[0].length] == '}');
            if (!(doubleFirst && doubleLast)) {
                placeholders.add(match[1]); // Add the captured group (the name)
            }
        }
        return placeholders;
    }

    function updateRiskDisplays(changes) {
        createRiskDisplays($('body')); // TODO: More specific element
    }

    function createRiskDisplays(el) {
        const requests = {}; // Batch object for all API requests
        const allPageState = window.RT.pagestate.allPageState();

        // All the class="RiskiUI RiskDisplay" elements under el:
        el.find('.RiskiUI.RiskDisplay').each(function(index, element) {
            let e = $(element);
            const id = e.attr('id');

            try {
                const originaltext = mw.riskutils.hexToString(e.data('originaltexthex') || '');
                const paramMap = JSON.parse(mw.riskutils.hexToString(e.data('paramshex') || '7b7d')); // hex for {}

                const definedParams = new Set(Object.keys(paramMap));
                definedParams.add('pagestate'); // Always defined by the server

                // 1. Find ALL placeholders, from the main text AND from all parameter expressions
                const allPlaceholders = new Set(matchPlaceholders(originaltext));
                Object.values(paramMap).forEach(expression => {
                    matchPlaceholders(expression).forEach(p => allPlaceholders.add(p));
                });

                // 2. Determine external dependencies
                const externalDependencies = new Set();
                for (const p of allPlaceholders) {
                    if (!definedParams.has(p)) {
                        externalDependencies.add(p);
                    }
                }

                // 3. Determine which dependencies are "unresolved"
                const unresolved = [];
                for (const dep of externalDependencies) {
                    if (!(dep in allPageState)) {
                        unresolved.push(dep);
                    }
                }
                if (unresolved.length > 0) {
                    // 5. We are waiting on unresolved placeholders.
                    e.data('lastStateStr', null); // Clear last saved state

                    const placeholderHtmlHex = e.data('placeholderhtmlhex') || '';
                    if (mw.riskutils.isDebugEnabled()) {
                        e.html('<pre>RiskDisplay waiting on:\n' + unresolved.join('\n') + '</pre>');
                    } else {
                        e.html(mw.riskutils.hexToString(placeholderHtmlHex));
                    }
                } else {
                    // 4. We have all data needed. Check if relevant data has changed.
                    // 4a. Build a snapshot of the *relevant* external state
                    const relevantState = {};
                    for (const dep of externalDependencies) {
                        relevantState[dep] = allPageState[dep];
                    }
                    // 4b. Create a snapshot of all data that matters for this calculation:
                    // its definition (text, params) and the relevant external state.
                    const currentStateSnapshot = {
                        text: originaltext,
                        params: paramMap,
                        relevantState: relevantState
                    };
                    // 4c. Stringify this focused snapshot and compare
                    const currentStateStr = JSON.stringify(currentStateSnapshot);
                    const lastStateStr = e.data('lastStateStr');

                    if (currentStateStr === lastStateStr) {
                        return; // Nothing to do
                    }

                    // 4d. If proceeding, store this new snapshot
                    e.data('lastStateStr', currentStateStr);

                    // 4e. Build the *API request* (which needs the *full* pagestate)
                    const requestData = {
                        text: originaltext,
                        params: paramMap,
                        pagestate: allPageState
                    };

                    requests[id] = requestData;
                    e.html("<i>Calculating...</i>");
                }
            } catch (error) {
                console.error("Error processing RiskDisplay (id: " + id + "):", error);
                e.html('<span class="error">Error: Could not render RiskDisplay.</span>');
            }
        });

        // --- After the loop, process the batch ---
        if (Object.keys(requests).length > 0) {
            var pageTitle = mw.config.get('wgPageName');
            var namespace = mw.config.get('wgCanonicalNamespace');
            var fullTitle = namespace ? (namespace + ':' + pageTitle) : pageTitle;

            var api = new mw.Api();
            api.postWithToken('csrf', {
                action: 'riskparse',
                format: 'json',
                title: fullTitle,
                requests: JSON.stringify(requests) // Send the entire batch of structured objects
            }).then( ( data ) => {
                if (data.riskparse && data.riskparse.results) {
                    for (const [id, html] of Object.entries(data.riskparse.results)) {
                        const e = $('#' + id);
                        if (e.length) {
                            e.html(html ?? '');
                        }
                    }
                    mw.hook('wikipage.content').fire($('#mw-content-text'));
                }
            }).catch((error) => {
                console.error('API batch request failed:', error);
                for (const id of Object.keys(requests)) {
                    $('#' + id).html('<span class="error">Error: Unable to update risk display.</span>');
                }
            });
        }
    }

    mw.hook('riskiData.changed').add(updateRiskDisplays);
    mw.hook('wikipage.content').add(createRiskDisplays);
});
