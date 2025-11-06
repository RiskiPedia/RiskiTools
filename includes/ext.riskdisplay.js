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
            const doubleFirst = (match.index > 0) && (input[match.index-1] == '{');
            const doubleLast = (match.index+match[0].length+1 < input.length) &&
                  (input[match.index+match[0].length+1] == '}');
            if (!(doubleFirst && doubleLast)) {
                placeholders.add(match[1]); // Add the captured group (the name)
            }
        }
        return placeholders;
    }

    function updateRiskDisplays(changes) {
        createRiskDisplays($('body')); // TODO: should be The most appropriate element containing the content, such as #mw-content-text (regular content root) or #wikiPreview (live preview root)
    }

    function createRiskDisplays(el) {
        const requests = {}; // Batch object for all API requests
        const allPageState = window.RT.pagestate.allPageState();
        const externalParams = new Set(Object.keys(allPageState));

        // All the class="RiskDisplay" elements under el:
        el.find('.RiskDisplay').each(function(index, element) {
            let e = $(element);
            const id = e.attr('id');

            try {
                const originaltext = mw.riskutils.hexToString(e.data('originaltexthex') || '');
                const paramMap = JSON.parse(mw.riskutils.hexToString(e.data('paramshex') || '7b7d')); // hex for {}
                const definedParams = new Set(Object.keys(paramMap));
                definedParams.add('pagestate'); // Always defined

                // 1. Find ALL placeholders, from the main text AND from all parameter expressions
                const allPlaceholders = new Set(matchPlaceholders(originaltext));
                Object.values(paramMap).forEach(expression => {
                    matchPlaceholders(expression).forEach(p => allPlaceholders.add(p));
                });

                // 2. Determine which placeholders are "unresolved"
                // An unresolved placeholder is one that is NOT defined by the model
                // AND NOT provided by the external page state.
                const unresolved = [...allPlaceholders].filter(p =>
                    !definedParams.has(p) && !externalParams.has(p)
                );

                if (unresolved.length === 0) {
                    // 3. We have all data needed. Send to server for processing.
                    const requestData = {
                        text: originaltext,
                        params: paramMap,
                        pagestate: allPageState
                    };

                    // 4. Check if data has changed since last send to avoid redundant API calls
                    const requestDataStr = JSON.stringify(requestData);
                    const lastSentData = e.data('lastSentData');

                    if (requestDataStr === lastSentData) {
                        return; // Nothing to do
                    }

                    requests[id] = requestData;
                    e.data('lastSentData', requestDataStr);
                    e.html("<i>Calculating...</i>");

                } else {
                    // 5. We are waiting on unresolved placeholders.
                    e.data('lastSentData', ''); // Clear last sent data
                    if (mw.riskutils.isDebugEnabled()) {
                        e.html('<pre>RiskDisplay waiting on:\n' + unresolved.join('\n') + '</pre>');
                    } else {
                        e.html(mw.riskutils.hexToString(e.data('placeholderhtmlhex')));
                    }
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
                requests: JSON.stringify(requests) // Send the entire batch
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
