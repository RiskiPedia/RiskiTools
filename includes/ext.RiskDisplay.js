mw.loader.using(['oojs-ui', 'ext.cookie'], function () {
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
                e.text(updatedText);
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

    // Listen for cookie changes via custom hook
    mw.hook('riskiData.changed').add(updateRiskDisplays);
});
