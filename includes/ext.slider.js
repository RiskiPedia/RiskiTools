// extensions/RiskiTools/includes/ext.slider.js
mw.loader.using(['oojs-ui'], function () {
    'use strict';

    function createSliders($content) {
        var $sliders = $content.find( '.riski-slider' );
        console.log( '[Slider] Found .riski-slider elements:', $sliders.length );

        $sliders.each( function () {
            var $container = $( this );
            console.log( '[Slider] Processing container:', $container[0] );

            var min = parseInt( $container.data( 'min' ), 10 ) || 0;
            var max = parseInt( $container.data( 'max' ), 10 ) || 100;
            var value = parseInt( $container.data( 'default' ), 10 ) || min;

            // Clamp value
            if ( max < min ) max = min;
            if ( value < min ) value = min;
            if ( value > max ) value = max;

            // Create Codex NumberInput as slider
            try {
                console.log( '[Slider] Creating OO.ui.NumberInputWidget …' );
                var numberInput = new OO.ui.NumberInputWidget( {
                    min: min,
                    max: max,
                    value: value,
                    step: 1,
                    range: true,
                    indicator: 'default',
                    classes: [ 'riski-slider-input' ]
                } );
                console.log( '[Slider] Widget created', numberInput );
            } catch ( err ) {
                console.error( '[Slider] Failed to create widget', err );
                return;
            }

            // Append to container
            $container.append( numberInput.$element );

            // Expose value via data for external use
            $container.data( 'current-value', value );
        } );
    }
    console.log( '[Slider] hooking into wikipage.content' );
    mw.hook('wikipage.content').add(createSliders);
});
