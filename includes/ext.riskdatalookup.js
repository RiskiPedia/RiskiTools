//
// RiskDataLookup tags are represented in the HTML page as:
// <span class="RiskDataLookup" data-paramshex=... hidden></span>
// On load we just need to put the key/value pairs into the page state:
// 
mw.loader.using(['ext.riskutils','ext.pagestate'], function () {
    function updateRiskDataLookup(el) {
        const allInitialStateChanges = {}; // Accumulator for all states
        el.find('.RiskDataLookup').each(function (index, element) {
            let e = $(element);
            if (e.data('processed')) {
                return; // Skip if already processed
            }
	    const kvpairs = JSON.parse(mw.riskutils.hexToString(e.data('paramshex')));
            // Object.assign merges the new state into our accumulator
            Object.assign(allInitialStateChanges, kvpairs);
            e.data('processed', true); // Mark as processed
        });
        RT.pagestate.setPageStates(allInitialStateChanges);
    }
    mw.hook('wikipage.content').add(updateRiskDataLookup);
});
