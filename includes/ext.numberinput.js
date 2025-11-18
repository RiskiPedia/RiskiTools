// extensions/RiskiTools/includes/ext.numberinput.js
mw.loader.using( ['ext.pagestate','ext.riskutils'] ).then( function () {
    'use strict';

    function initNumberInputs( $content ) {
        const allInitialStateChanges = {}; // Accumulator for all states

        $content.find( '.riski-number-input' ).each( function () {
            var $input = $( this );
            var statevar = ($input.attr("name") || '').trim();

            if (statevar) {
                if (RT.pagestate.hasPageState(statevar)) {
                    $input.val(RT.pagestate.getPageState(statevar));
                } else {
                    Object.assign(allInitialStateChanges, { [statevar] : $input.val() });
                }
            }

            // Final save when user is "done" (blur, Enter, arrows finish)
            if (statevar) {
                $input.on( 'change', function () {
                    var val = this.value;
                    RT.pagestate.setPageState(statevar, val);
                } );
            }
        } );
        // After the loop, set all initial states at once.
        if (Object.keys(allInitialStateChanges).length > 0) {
            RT.pagestate.setPageStates(allInitialStateChanges);
        }
    }

    mw.hook( 'wikipage.content' ).add( initNumberInputs );
} );
