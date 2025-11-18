// extensions/RiskiTools/includes/ext.numberinput.js
mw.loader.using( [] ).then( function () {
    'use strict';

    function initNumberInputs( $content ) {
        $content.find( '.riski-number-input' ).each( function () {
            var $input = $( this );

            // Final save when user is "done" (blur, Enter, arrows finish)
            $input.on( 'change', function () {
                var val = this.value;

                var name = this.getAttribute( 'name' );
                if ( name && name.trim() !== '' ) {
                    mw.hook( 'riskitools.statechange' ).fire( name, parseInt( val, 10 ) );
                }
            } );

            // Initial state exposure
            var initialName = $input.attr( 'name' );
            if ( initialName && initialName.trim() !== '' ) {
                mw.hook( 'riskitools.statechange' ).fire( initialName, parseInt( $input.val(), 10 ) );
            }
        } );
    }

    mw.hook( 'wikipage.content' ).add( initNumberInputs );
} );
