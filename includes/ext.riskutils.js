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
        const bytes = new Uint8Array(hex.match(/[\da-f]{2}/gi).map(h => parseInt(h, 16)));
        return new TextDecoder().decode(bytes);
    }

    utils.isDebugEnabled = function() {
        const params = new URLSearchParams( window.location.search );
        return params.get( 'debug' ) === '1';
    }

    /**
     * Tags a jQuery element with the page state keys it manages.
     * This is used by the MutationObserver to clean up the pagestate when
     * the element is removed from the DOM.
     *
     * @param {jQuery} $el The jQuery element to tag.
     * @param {string[]} keys An array of page state key names.
     */
    utils.setManagedPageKeys = function($el, keys) {
        if (!$el || typeof $el.data !== 'function') {
            console.error('riskutils.setManagedPageKeys: Invalid jQuery element provided.');
            return;
        }
        if (!Array.isArray(keys)) {
            console.error('riskutils.setManagedPageKeys: Keys must be an array.');
            return;
        }
        $el.data('managed-pagestate-keys', keys);
        $el.attr('data-managed-pagestate-keys', JSON.stringify(keys));
    }

}(mw.riskutils));

mw.loader.using([], function () {
    // Create an observer to watch for removals and clean up pagestate
    const observer = new MutationObserver(function(mutations) {

        // Use a Set to collect unique keys from all removed elements in this batch
        const allKeysToDelete = new Set();

        mutations.forEach(function(mutation) {
            // Check if any nodes were removed
            mutation.removedNodes.forEach(function(node) {
                // We only care about element nodes
                if (node.nodeType !== 1) {
                    return;
                }

                // Check if the removed node itself, or any of its children,
                // has the 'managed-pagestate-keys' data attribute.
                const $node = $(node);
                const $managedElements = $node.find('[data-managed-pagestate-keys]')
                      .add($node.filter('[data-managed-pagestate-keys]'));

                // Accumulate all keys into the Set
                $managedElements.each(function() {
                    const $el = $(this);

                    const keysString = $el.attr('data-managed-pagestate-keys');
                    if (keysString) {
                        try {
                            const managedKeys = JSON.parse(keysString); // managedKeys is an array
                            if (managedKeys && managedKeys.length > 0) {
                                managedKeys.forEach(key => allKeysToDelete.add(key));
                            }
                        } catch (e) {
                            console.error('Observer: Failed to parse managed-pagestate-keys', e);
                        }
                    }
                });
            });
        });

        // After checking all mutations, make a single delete call if needed
        if (allKeysToDelete.size > 0) {
            const keysArray = Array.from(allKeysToDelete);

            // Call deletePageStates ONCE with all unique keys
            RT.pagestate.deletePageStates(keysArray);
        }
    });

    // Start observing the main wiki content area for changes
    const targetNode = document.getElementById('mw-content-text') || document.body;
    observer.observe(targetNode, {
        childList: true, // Watch for nodes being added or removed
        subtree: true    // Watch all descendants of the target
    });
});
