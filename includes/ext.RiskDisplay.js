
mw.loader.using(['oojs-ui'], function () {
    // Now OOUI is loaded and we can use it

    // Make keys/values safe for the Template syntax
    function escapeForTemplate(str) {
        return String(str).replace(/[^a-zA-Z0-9_+,.!@#$%^*:;\- ]/g, ch => `&#${ch.charCodeAt(0)};`);
    }

    function replacePlaceholders(text, data) {
        let result = text;
        for (const [key, value] of Object.entries(data)) {
            // Replace all occurrences of {{key}}  or {key} with the value
            result = result.replaceAll(`{{${key}}}`, value);
            result = result.replaceAll(`{${key}}`, value);
        }
        return result;
    }

    /**
     * Decodes a hex-encoded UTF-8 string back to its original form.
     * @param {string} hex - The hex-encoded string.
     * @returns {string} The decoded string.
     */
    function hexToString(hex) {
        const bytes = new Uint8Array(hex.match(/[\da-f]{2}/gi).map(h => parseInt(h, 16)));
        return new TextDecoder().decode(bytes);
    }

    function updateRiskDisplays() {
        // All the class="RiskDisplay" elements on the page...
        $('.RiskDisplay').each(function(index, element) {
	    let e = $(element);
	    const originaltext = hexToString(e.data('originaltexthex'));
	    const id = e.attr('id');
            const jscode = e.data('jscode');

            try {
                const result = eval(jscode); // jscode is server-generated, so we know it's safe

                // Let editors use either {{result}} (like a WikiMedia Template named param) or
                // just {result}:
                let updatedText = replacePlaceholders(originaltext, { 'result' : result });

                // Make page state available to Templates (or whatever) by replacing {{pagestate}}
                // with Template-argument-friendly key1=value1|key2=value2|..etc
                const allPageState = window.RT.pagestate.allPageState();
                const ps = Object.entries(allPageState)
                        .map(([k, v]) => `${escapeForTemplate(k)}=${escapeForTemplate(v)}`)
                        .join('|');
                updatedText = replacePlaceholders(updatedText, { 'pagestate' : ps });

                // And replace the individual pagestate {{key}} with their value:
                updatedText = replacePlaceholders(updatedText, allPageState);

                // Send the text to the server to parse (surrounded by a unique string
                // because we want to strip out the extraneous div's and p's the server
                // wraps it in)
                const uniquetext = 'z3IP5fEV3B9qSE';
                const wikitext = uniquetext+updatedText+uniquetext;
                var api = new mw.Api();
                api.get( {
                    action: 'parse',
                    format: 'json',
                    formatversion: 2,
                    contentmodel: 'wikitext',
                    text: wikitext,
                    prop: 'text'
                } ).then( ( data ) => {
                    const r = data.parse.text;
                    const startIndex = r.indexOf(uniquetext);
                    const endIndex = r.lastIndexOf(uniquetext);
                    e.text(r.substring(startIndex + uniquetext.length, endIndex));
                } ).catch((error) => {
                    e.text('Error: Unable to update risk display');
                    console.error('API request failed:', error);
                });
            } catch (error) {
                e.text('');
            }
        });
    }

    // Initial update
    updateRiskDisplays();

    // Listen for UI data changes via custom hook
    mw.hook('riskiData.changed').add(updateRiskDisplays);
});
