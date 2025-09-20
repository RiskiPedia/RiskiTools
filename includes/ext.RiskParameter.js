//
// RiskParameters are represented in the HTML page as:
// <span class="RiskParameter" hidden>{key1=value1, key2=value2}</span>
// On load we just need to put the key/value pairs into the page state:
// 
mw.loader.using(['oojs-ui', 'ext.cookie', 'ext.pagestate'], function () {
    function updateRiskParams() {
        $('.RiskParameter').each(function (index, element) {
            let e = $(element);
	    const data = JSON.parse(e.text());
            RT.pagestate.setPageStates(data);
        });
    }
    updateRiskParams();
    mw.hook('riskiUI.changed').add(updateRiskParams);
});
