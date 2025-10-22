//
// RiskDataLookup tags are represented in the HTML page as:
// <span class="RiskDataLookup" data-paramshex=... hidden></span>
// On load we just need to put the key/value pairs into the page state:
// 
mw.loader.using(['ext.riskutils','ext.pagestate'], function () {
    function updateRiskDataLookup() {
        $('.RiskDataLookup').each(function (index, element) {
            let e = $(element);
            if (e.data('processed')) {
                return; // Skip if already processed
            }
	    const kvpairs = JSON.parse(mw.riskutils.hexToString(e.data('paramshex')));
            RT.pagestate.setPageStates(kvpairs);
            e.data('processed', true); // Mark as processed
        });
    }
    updateRiskDataLookup();
    mw.hook('riskiUI.changed').add(updateRiskDataLookup);
});
