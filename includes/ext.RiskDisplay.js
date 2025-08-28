mw.loader.using(['oojs-ui'], function () {
    // Now OOUI is loaded and we can use it

    function updateRiskDisplays() {
        // All the class="RiskDisplay" elements on the page...
        $('.RiskDisplay').each(function(index, element) {
	    let e = $(element);
	    const originalText = e.data('originalText');
	    const id = e.attr('id');
            const jscode = e.data('jscode');

            try {
                const result = eval(jscode); // jscode is server-generated, so we know it's safe
                const updatedText = originalText.replace(/{result}/g, result);

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

    // Store original text and initialize
    $('.RiskDisplay').each(function (index, element) {
        let e = $(element);
        e.data('originalText', e.text()); // Store original text with {result}
    });

    // Initial update
    updateRiskDisplays();

    // Listen for UI data changes via custom hook
    mw.hook('riskiData.changed').add(updateRiskDisplays);
});
