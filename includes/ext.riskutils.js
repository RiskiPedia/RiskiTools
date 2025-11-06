// Create a namespace to avoid global scope pollution
mw.riskutils = mw.riskutils || {};

(function (utils) {
    'use strict';

    /**
     * Decodes a hex-encoded UTF-8 string back to its original form.
     * @param {string} hex - The hex-encoded string.
     * @returns {string} The decoded string.
     */
    utils.hexToString = function(hex) {
        if (!hex || hex.length < 2) return '';
        const bytes = new Uint8Array(hex.match(/[\da-f]{2}/gi).map(h => parseInt(h, 16)));
        return new TextDecoder().decode(bytes);
    }

    utils.isDebugEnabled = function() {
        const params = new URLSearchParams( window.location.search );
        return params.get( 'debug' ) === '1';
    }

    utils.debugPrint = function(s) {
        if (utils.isDebugEnabled()) {
            console.log(s);
        }
    }

    utils.parentFrame = function() {
        const e = new Error();
        // The stack is a string, split by newlines
        const lines = e.stack.split('\n');
        // line 0: "Error"
        // line 1: debugPrintCaller() (this function)
        // line 2: calling function
        // line 3: parent of calling function (the one we want to print)
        return lines[3].trim();
    }
}(mw.riskutils));
