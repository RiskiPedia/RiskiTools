// extensions/RiskiTools/includes/ext.slider.js
mw.loader.using( [] ).then( function () {
    'use strict';

    function initSliders( $content ) {
        $content.find( '.riski-slider-native' ).each( function () {
            var $slider = $( this );

            // Create live value span
            var $value = $( '<span>' )
                .addClass( 'riski-slider-value' )
                .text( $slider.val() );

            // Insert after slider
            $slider.after( $value );

            // Update on input (drag or keyboard)
            $slider.on( 'input', function () {
                var val = this.value;
                this.title = val;
                $value.text( val );
                // Expose to other scripts
                $slider.closest( '.riski-slider' ).data( 'current-value', val );
            } );

            // Initial data exposure
            $slider.closest( '.riski-slider' ).data( 'current-value', $slider.val() );
        } );
    }

    mw.hook( 'wikipage.content' ).add( initSliders );
} );
