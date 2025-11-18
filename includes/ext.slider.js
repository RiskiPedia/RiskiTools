// extensions/RiskiTools/includes/ext.slider.js
mw.loader.using( [ 'ext.pagestate','ext.riskutils' ] ).then( function () {
    'use strict';

    function initSliders( $content ) {
        const allInitialStateChanges = {}; // Accumulator for all states

        $content.find( '.riski-slider-needsinit' ).each( function () {
            var $slider = $( this );
            $slider.removeClass('riski-slider-needsinit');

            var statevar = ($slider.attr("name") || '').trim();
            if (statevar) {
                if (RT.pagestate.hasPageState(statevar)) {
                    $slider.val(RT.pagestate.getPageState(statevar));
                } else {
                    Object.assign(allInitialStateChanges, { [statevar] : $slider.val() });
                }
            }

            // Create live value span
            var $valueSpan = $( '<span>' )
                .addClass( 'riski-slider-value' )
                .text( $slider.val() );

            // Insert after slider
            $slider.after( $valueSpan );

            // Update on input (drag or keyboard)
            $slider.on( 'input', function () {
                var val = this.value;
                this.title = val;
                $valueSpan.text( val );
                // Expose to other scripts
                $slider.closest( '.riski-slider' ).data( 'current-value', val );
            } );
            if (statevar) {
                $slider.on( 'change', function () {
                    var val = this.value;
                    RT.pagestate.setPageState(statevar, val);
                } );
            }
            // Initial data exposure
            $slider.closest( '.riski-slider' ).data( 'current-value', $slider.val() );
        } );
        // After the loop, set all initial states at once.
        if (Object.keys(allInitialStateChanges).length > 0) {
            RT.pagestate.setPageStates(allInitialStateChanges);
        }
    }
    mw.hook( 'wikipage.content' ).add( initSliders );
    // TODO: have sliders change when pagestate changes...
    // Can I generalize? Have code that handles all RiskUI-class-elements?
    //  ... maybe if they have an onStateChange?
} );
