
mw.loader.using(['ext.riskutils','ext.dropdown','ext.riskparameter','oojs-ui'], function () {
    // Now OOUI is loaded and we can use it

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

            return !isDoubleOpen && !isDoubleClose;
        });
        return validMatches.map(m => m.slice(1, -1));
    }

    function updateRiskDisplays() {
        // All the class="RiskDisplay" elements on the page...
        $('.RiskDisplay').each(function(index, element) {
	    let e = $(element);
	    const originaltext = mw.riskutils.hexToString(e.data('originaltexthex'));
	    const id = e.attr('id');

            try {
                let updatedText = originaltext;

                // Make page state available to Templates (or whatever) by replacing {pagestate}
                // with Template-argument-friendly key1=value1|key2=value2|..etc
                const allPageState = window.RT.pagestate.allPageState();
                const ps = Object.entries(allPageState)
                        .map(([k, v]) => `${escapeForTemplate(k)}=${escapeForTemplate(v)}`)
                        .join('|');
                updatedText = replacePlaceholders(updatedText, { 'pagestate' : ps });

                // These are all the {placeholders} we need before we can display content:
                const allPlaceholders = matchPlaceholders(updatedText);

                // And replace the individual pagestate {key} with their value:
                updatedText = replacePlaceholders(updatedText, allPageState);

                const placeholders = matchPlaceholders(updatedText);
                if (placeholders.length == 0) { // No placeholders left:
                    // Send the text to the server to parse (surrounded by a unique string
                    // because we want to strip out the extraneous div's and p's the server
                    // wraps it in)
                    const uniquetext = 'z3IP5fEV3B9qSE';
                    const wikitext = uniquetext+updatedText+uniquetext;

                    // Get the previously sent wikitext from the element's data store.
                    const lastSentWikitext = e.data('lastSentWikitext');

                    // If the wikitext hasn't changed, do nothing. This avoids a needless API call/ display refresh.
                    if (wikitext === lastSentWikitext) {
                        return; // Skips to the next element in the .each() loop.
                    }
                    e.data('lastSentWikitext', wikitext);

                    // Get current page context
                    var pageTitle = mw.config.get('wgPageName');
                    var namespace = mw.config.get('wgCanonicalNamespace');
                    var fullTitle = namespace ? (namespace + ':' + pageTitle) : pageTitle;

                    var api = new mw.Api();
                    e.html("<i>Calculating...</i>");
                    api.get( {
                        action: 'parse',
                        format: 'json',
                        formatversion: 2,
                        title: fullTitle,
                        pst: true,
                        text: wikitext,
                        prop: 'text'
                    } ).then( ( data ) => {
                        const r = data.parse.text;
                        const startIndex = r.indexOf(uniquetext);
                        const endIndex = r.lastIndexOf(uniquetext);
                        const newhtml = r.substring(startIndex + uniquetext.length, endIndex);
                        e.html(newhtml);
                        if (newhtml.trim() !== '') {
                            mw.hook('riskiUI.changed').fire(); // Trigger any new UI elements
                        }
                    } ).catch((error) => {
                        e.text('Error: Unable to update risk display');
                        console.error('API request failed:', error);
                    });
                } else if (mw.riskutils.isDebugEnabled()) {
                    e.html('<pre>RiskDisplay waiting on:\n'+placeholders.join('\n')+'</pre>');
                } else {
                    e.html(mw.riskutils.hexToString(e.data('placeholderhtmlhex')));
                }
            } catch (error) {
                e.text('');
            }
        });
    }

    // Initial update
    updateRiskDisplays();

    // Listen for UI data changes via custom hook
    mw.hook('riskiData.changed').add(updateRiskDisplays);
    mw.hook('riskiUI.changed').add(updateRiskDisplays);
});
