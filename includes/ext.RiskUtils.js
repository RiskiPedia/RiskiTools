// Create a namespace to avoid global scope pollution
mw.RiskUtils = mw.RiskUtils || {};

(function (utils) {
    'use strict';

    /**
     * Decodes a hex-encoded UTF-8 string back to its original form.
     * @param {string} hex - The hex-encoded string.
     * @returns {string} The decoded string.
     */
    utils.hexToString = function(hex) {
        const bytes = new Uint8Array(hex.match(/[\da-f]{2}/gi).map(h => parseInt(h, 16)));
        return new TextDecoder().decode(bytes);
    }

}(mw.RiskUtils));

