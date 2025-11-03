//
// RiskParameters are represented in the HTML page as:
// <span class="RiskParameter" hidden>key1=value1
// key2=value2</span>
// On load we just need to put the key/value pairs into the page state:
// 
mw.loader.using(['ext.pagestate'], function () {
    function createRiskParams(el) {
        const allInitialStateChanges = {}; // Accumulator for all states
        el.find('.RiskParameter').each(function (index, element) {
            let e = $(element);
            e.removeClass('RiskParameter'); // So we don't process this element again

            // Split by newlines and filter out empty lines
            var lines = e.text().split('\n').filter(function(line) {
                return line.trim() !== '';
            });
            var result = {};
            lines.forEach(function(line) {
                // Skip lines without = or split into parts
                var parts = line.split('=', 2);
                if (parts.length < 2) {
                    return;
                }
                var key = parts[0].trim();
                var value = parts[1];
                result[key] = value;
            });
            // Object.assign merges the new state into our accumulator
            Object.assign(allInitialStateChanges, result);
        });
        RT.pagestate.setPageStates(allInitialStateChanges);
    }
    mw.hook('wikipage.content').add(createRiskParams);
});
