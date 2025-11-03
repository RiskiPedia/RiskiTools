mw.loader.using(['ext.riskutils', 'ext.dropdown', 'ext.riskparameter', 'oojs-ui'], function () {
    'use strict';

    // Make keys/values safe for the Template syntax
    function escapeForTemplate(str) {
        return String(str).replace(/[^a-zA-Z0-9_+,.!@#$%^*:;\- ]/g, ch => `&#${ch.charCodeAt(0)};`);
    }

    function replacePlaceholders(text, data) {
        let result = text;
        for (const [key, value] of Object.entries(data)) {
            // Replace all occurrences of {key} with the value
            result = result.replaceAll(`{${key}}`, value);
        }
        return result;
    }

    function matchPlaceholders(input) {
        // Match { followed by allowed chars (letters, numbers, underscores, marks) then }
        const potentialMatches = input.match(/\{[\p{L}\p{N}_\p{M}]+\}/gu) || [];

        // Filter out double braces {{...}} and {...}}
        // because those are template calls.
        const validMatches = potentialMatches.filter(match => {
            const startIndex = input.indexOf(match);
            const before = input.slice(0, startIndex);
            const after = input.slice(startIndex + match.length);

            const isDoubleOpen = before.endsWith('{');
            const isDoubleClose = after.startsWith('}');

            return !(isDoubleOpen && isDoubleClose);
        });
        return validMatches.map(m => m.slice(1, -1));
    }

    function updateRiskDisplays(changes) {
        createRiskDisplays($('body')); // TODO: should be The most appropriate element containing the content, such as #mw-content-text (regular content root) or #wikiPreview (live preview root)
    }

    function createRiskDisplays(el) {
        const requests = {}; // Batch object for all API requests

        // All the class="RiskDisplay" elements under el:
        el.find('.RiskDisplay').each(function(index, element) {
            let e = $(element);
            const originaltext = mw.riskutils.hexToString(e.data('originaltexthex'));
            const id = e.attr('id');

            try {
                let updatedText = originaltext;

                // Make page state available
                const allPageState = window.RT.pagestate.allPageState();
                const ps = Object.entries(allPageState)
                            .map(([k, v]) => `${escapeForTemplate(k)}=${escapeForTemplate(v)}`)
                            .join('|');
                updatedText = replacePlaceholders(updatedText, { 'pagestate' : ps });

                // And replace the individual pagestate {key} with their value:
                updatedText = replacePlaceholders(updatedText, allPageState);

                const placeholders = matchPlaceholders(updatedText);

                if (placeholders.length == 0) { // No placeholders left:
                    const lastSentWikitext = e.data('lastSentWikitext');

                    if (updatedText === lastSentWikitext) {
                        return;
                    }
                    requests[id] = updatedText;
                    e.data('lastSentWikitext', updatedText);
                    e.html("<i>Calculating...</i>");

                } else {
                    e.data('lastSentWikitext', '');
                    if (mw.riskutils.isDebugEnabled()) {
                        e.html('<pre>RiskDisplay waiting on:\n'+placeholders.join('\n')+'</pre>');
                    } else {
                        e.html(mw.riskutils.hexToString(e.data('placeholderhtmlhex')));
                    }
                }
            } catch (error) {
                e.text('');
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
                    $('#' + id).text('Error: Unable to update risk display');
                }
            });
        }
    }

    mw.hook('riskiData.changed').add(updateRiskDisplays);
    mw.hook('wikipage.content').add(createRiskDisplays);
});
