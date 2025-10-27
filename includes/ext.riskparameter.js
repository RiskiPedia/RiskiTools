//
// RiskParameters are represented in the HTML page as:
// <span class="RiskParameter" hidden>key1=value1
// key2=value2</span>
// On load we just need to put the key/value pairs into the page state:
// 
mw.loader.using(['ext.pagestate'], function () {
    function createRiskParams(el) {
        el.find('.RiskParameter').each(function (index, element) {
            let e = $(element);
            if (e.data('processed')) {
                return; // Skip if already processed
            }

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
            RT.pagestate.setPageStates(result);
            e.data('processed', true); // Mark as processed
        });
    }
    mw.hook('wikipage.content').add(createRiskParams);
});
