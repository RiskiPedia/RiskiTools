//
// Store key/value pairs in state
// associated with a single page.
//
// Use this for transient UI state that
// shouldn't persist across pages or sessions.
//
const p = {
    hasPageState: function (name) {
        const riskiData = window.RT.pagedata || {};
        return (name in riskiData);
    },
    getPageState: function (name) {
        const riskiData = window.RT.pagedata || {};
        if (!(name in riskiData)) {
            throw new Error(`pagestate "${name}" is not set`);
        }
        return riskiData[name];
    },
    allPageState: function() {
        return window.RT.pagedata;
    },
    setPageStates: function (nameValuePairs) {
        if (!nameValuePairs || typeof nameValuePairs !== 'object') {
            console.error('RT.setPageStates: Invalid nameValuePairs:', nameValuePairs);
            return;
        }
        if (Object.keys(nameValuePairs).length === 0) {
            return;
        }

        mw.riskutils.debugPrint("setPageStates " + JSON.stringify(nameValuePairs));
        mw.riskutils.debugPrint(mw.riskutils.parentFrame());

        const riskiData = window.RT.pagedata || {};
        let stateChanged = false; // Flag to track if any value actually changed

        for (const [name, value] of Object.entries(nameValuePairs)) {
            if (typeof name !== 'string' || name.trim() === '' || value === undefined) {
                console.error('RT.setPageStates: Invalid name or value:', name, value);
                continue;
            }
            if (riskiData[name] !== value) {
                riskiData[name] = value;
                stateChanged = true; // Mark that a change occurred
            }
        }
        if (stateChanged) {
            mw.hook('riskiData.changed').fire(nameValuePairs);
        }
    },
    setPageState: function (name, value) {
        this.setPageStates({ [name]: value });
    },
    deletePageStates: function (names) {
        if (!Array.isArray(names)) {
            console.error('RT.deletePageStates: Invalid names array:', names);
            return;
        }
        if (names.length === 0) {
            return;
        }

        mw.riskutils.debugPrint("deletePageStates " + JSON.stringify(names));
        mw.riskutils.debugPrint(mw.riskutils.parentFrame());

        const riskiData = window.RT.pagedata || {};
        let stateChanged = false;

        for (const name of names) {
            if (typeof name !== 'string' || name.trim() === '') {
                console.error('RT.deletePageStates: Invalid name:', name);
                continue;
            }
            // Check if the key actually exists before deleting
            if (name in riskiData) {
                delete riskiData[name];
                stateChanged = true; // A key was successfully deleted
            }
        }
        if (stateChanged) {
            mw.hook('riskiData.changed').fire(names);
        }
    },
    deletePageState: function (name) {
        this.deletePageStates([name]);
    },
};

window.RT = window.RT || {};
window.RT.pagestate = p;
window.RT.pagedata = window.RT.pagedata || {};

mw.loader.using(['ext.riskutils'], function () {
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
