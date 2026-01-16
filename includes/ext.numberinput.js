// extensions/RiskiTools/includes/ext.numberinput.js
mw.loader.using(['ext.pagestate', 'ext.riskutils']).then(function () {
    'use strict';

    function initNumberInputs($content) {
        const initialState = {};

        $content.find('.riski-number-input').each(function () {
            const $input = $(this);
            const name = ($input.attr('name') || '').trim();

            if (!name) return; // Skip if no statevar not set

            const currentValue = $input.val().trim();

            // Restore saved value if exists
            if (RT.pagestate.hasPageState(name)) {
                const saved = RT.pagestate.getPageState(name);
                if (saved !== currentValue) {
                    $input.val(saved);
                }
                // No need to re-fire — pagestate already knows
            } else if (currentValue != "") {
                // Queue initial value for batch set
                initialState[name] = currentValue;
            }
            // Save on change (blur, Enter, arrows) - user interaction
            $input.on('change', function () {
                if (this.value != "") {
                    if (this.value < $input.attr('min')) {
                        this.value = $input.attr('min');
                    } else if (this.value > $input.attr('max')) {
                        this.value = $input.attr('max');
                    }
                    RT.pagestate.setUserChoice(name, this.value); // User choice - updates URL hash
                } else {
                    RT.pagestate.deletePageState(name);
                }
            });
        });
        // Batch-initialize all new variables
        if (Object.keys(initialState).length) {
            RT.pagestate.setPageStates(initialState);
        }
    }
    mw.hook('wikipage.content').add(initNumberInputs);
});
