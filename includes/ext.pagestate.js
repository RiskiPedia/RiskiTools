//
// Store key/value pairs in state
// associated with a single page.
//
// Use this for transient UI state that
// shouldn't persist across pages or sessions.
//

/**
 * Check if a value is a scalar (string, number, or boolean) suitable for URL hash encoding.
 * @param {*} value - The value to check
 * @return {boolean} True if the value is scalar
 */
function isScalar( value ) {
    const type = typeof value;
    return value !== null && value !== undefined && ( type === 'string' || type === 'number' || type === 'boolean' );
}

/**
 * Update the URL hash to reflect current user choice state.
 * Only includes keys that are marked as user choices.
 * Uses history.replaceState to avoid creating new history entries.
 */
function updateHashFromState() {
    const riskiData = window.RT.pagedata || {};
    const userChoiceKeys = window.RT.userChoiceKeys || new Set();
    const pairs = [];

    for ( const key of userChoiceKeys ) {
        if ( key in riskiData ) {
            const value = riskiData[ key ];
            if ( isScalar( value ) ) {
                pairs.push( encodeURIComponent( key ) + '=' + encodeURIComponent( String( value ) ) );
            }
        }
    }

    const newHash = pairs.length > 0 ? '#' + pairs.join( '&' ) : '';
    const newUrl = window.location.pathname + window.location.search + newHash;
    window.history.replaceState( null, '', newUrl );
}

const p = {
    hasPageState: function ( name ) {
        const riskiData = window.RT.pagedata || {};
        return ( name in riskiData );
    },
    getPageState: function ( name ) {
        const riskiData = window.RT.pagedata || {};
        if ( !( name in riskiData ) ) {
            throw new Error( `pagestate "${name}" is not set` );
        }
        return riskiData[ name ];
    },
    allPageState: function () {
        return window.RT.pagedata;
    },

    /**
     * Check if a key is marked as a user choice (and thus included in shareable URLs).
     * @param {string} name - The key to check
     * @return {boolean} True if the key is a user choice
     */
    isUserChoice: function ( name ) {
        const userChoiceKeys = window.RT.userChoiceKeys || new Set();
        return userChoiceKeys.has( name );
    },

    /**
     * Parse a URL hash string into key-value pairs.
     * @param {string} [hash] - The hash string (e.g., '#key=value&key2=value2'). If omitted, returns empty object.
     * @return {Object} An object with the parsed key-value pairs
     */
    parseHashState: function ( hash ) {
        const result = {};
        if ( !hash || hash === '#' ) {
            return result;
        }

        // Remove leading '#' if present
        const hashContent = hash.startsWith( '#' ) ? hash.substring( 1 ) : hash;
        if ( !hashContent ) {
            return result;
        }

        const pairs = hashContent.split( '&' );
        for ( const pair of pairs ) {
            const eqIndex = pair.indexOf( '=' );
            if ( eqIndex === -1 ) {
                // No equals sign, skip this entry
                continue;
            }

            const key = decodeURIComponent( pair.substring( 0, eqIndex ) );
            const value = decodeURIComponent( pair.substring( eqIndex + 1 ) );

            // Skip empty keys
            if ( key === '' ) {
                continue;
            }

            result[ key ] = value;
        }

        return result;
    },

    /**
     * Generate a shareable URL with current user choice state encoded in the hash.
     * Only includes keys that are marked as user choices and have scalar values.
     * @return {string} The full URL with hash fragment
     */
    getShareableURL: function () {
        const riskiData = window.RT.pagedata || {};
        const userChoiceKeys = window.RT.userChoiceKeys || new Set();
        const pairs = [];

        for ( const key of userChoiceKeys ) {
            if ( key in riskiData ) {
                const value = riskiData[ key ];
                if ( isScalar( value ) ) {
                    pairs.push( encodeURIComponent( key ) + '=' + encodeURIComponent( String( value ) ) );
                }
            }
        }

        const hash = pairs.length > 0 ? '#' + pairs.join( '&' ) : '';
        return window.location.origin + window.location.pathname + window.location.search + hash;
    },

    /**
     * Load page state from the current URL hash.
     * This should be called early in page initialization.
     * Hash values will overwrite any existing pagedata values and are marked as user choices.
     */
    loadFromHash: function () {
        const hashState = this.parseHashState( window.location.hash );
        const riskiData = window.RT.pagedata || {};
        const userChoiceKeys = window.RT.userChoiceKeys || new Set();

        for ( const [ key, value ] of Object.entries( hashState ) ) {
            riskiData[ key ] = value;
            userChoiceKeys.add( key );
        }

        window.RT.pagedata = riskiData;
        window.RT.userChoiceKeys = userChoiceKeys;
    },

    /**
     * Set a single user choice value.
     * This sets the pagestate, marks the key as a user choice, and updates the URL hash.
     * Use this for values that come from user interaction (dropdowns, sliders, inputs).
     * @param {string} name - The key name
     * @param {*} value - The value to set
     */
    setUserChoice: function ( name, value ) {
        this.setUserChoices( { [ name ]: value } );
    },

    /**
     * Set multiple user choice values.
     * This sets the pagestate, marks all keys as user choices, and updates the URL hash.
     * Use this for values that come from user interaction (dropdowns, sliders, inputs).
     * @param {Object} nameValuePairs - An object with key-value pairs to set
     */
    setUserChoices: function ( nameValuePairs ) {
        if ( !nameValuePairs || typeof nameValuePairs !== 'object' ) {
            console.error( 'RT.setUserChoices: Invalid nameValuePairs:', nameValuePairs );
            return;
        }
        if ( Object.keys( nameValuePairs ).length === 0 ) {
            return;
        }

        const userChoiceKeys = window.RT.userChoiceKeys || new Set();

        // Mark all keys as user choices
        for ( const key of Object.keys( nameValuePairs ) ) {
            if ( typeof key === 'string' && key.trim() !== '' ) {
                userChoiceKeys.add( key );
            }
        }
        window.RT.userChoiceKeys = userChoiceKeys;

        // Set the pagestate (this will fire the hook if values changed)
        this.setPageStates( nameValuePairs );

        // Update the URL hash
        updateHashFromState();
    },

    setPageStates: function ( nameValuePairs ) {
        if ( !nameValuePairs || typeof nameValuePairs !== 'object' ) {
            console.error( 'RT.setPageStates: Invalid nameValuePairs:', nameValuePairs );
            return;
        }
        if ( Object.keys( nameValuePairs ).length === 0 ) {
            return;
        }

        mw.riskutils.debugPrint( 'setPageStates ' + JSON.stringify( nameValuePairs ) );
        mw.riskutils.debugPrint( mw.riskutils.parentFrame() );

        const riskiData = window.RT.pagedata || {};
        let stateChanged = false; // Flag to track if any value actually changed

        for ( const [ name, value ] of Object.entries( nameValuePairs ) ) {
            if ( typeof name !== 'string' || name.trim() === '' || value === undefined ) {
                console.error( 'RT.setPageStates: Invalid name or value:', name, value );
                continue;
            }
            if ( riskiData[ name ] !== value ) {
                riskiData[ name ] = value;
                stateChanged = true; // Mark that a change occurred
            }
        }
        if ( stateChanged ) {
            mw.hook( 'riskiData.changed' ).fire( nameValuePairs );
        }
    },
    setPageState: function ( name, value ) {
        this.setPageStates( { [ name ]: value } );
    },
    deletePageStates: function ( names ) {
        if ( !Array.isArray( names ) ) {
            console.error( 'RT.deletePageStates: Invalid names array:', names );
            return;
        }
        if ( names.length === 0 ) {
            return;
        }

        mw.riskutils.debugPrint( 'deletePageStates ' + JSON.stringify( names ) );
        mw.riskutils.debugPrint( mw.riskutils.parentFrame() );

        const riskiData = window.RT.pagedata || {};
        const userChoiceKeys = window.RT.userChoiceKeys || new Set();
        let stateChanged = false;
        let userChoiceDeleted = false;

        for ( const name of names ) {
            if ( typeof name !== 'string' || name.trim() === '' ) {
                console.error( 'RT.deletePageStates: Invalid name:', name );
                continue;
            }
            // Check if the key actually exists before deleting
            if ( name in riskiData ) {
                delete riskiData[ name ];
                stateChanged = true; // A key was successfully deleted

                // Also remove from user choice tracking
                if ( userChoiceKeys.has( name ) ) {
                    userChoiceKeys.delete( name );
                    userChoiceDeleted = true;
                }
            }
        }

        // Update URL hash if any user choices were deleted
        if ( userChoiceDeleted ) {
            updateHashFromState();
        }

        if ( stateChanged ) {
            mw.hook( 'riskiData.changed' ).fire( names );
        }
    },
    deletePageState: function ( name ) {
        this.deletePageStates( [ name ] );
    },
};

window.RT = window.RT || {};
window.RT.pagestate = p;
window.RT.pagedata = window.RT.pagedata || {};
window.RT.userChoiceKeys = window.RT.userChoiceKeys || new Set();

// Load initial state from URL hash before components initialize
p.loadFromHash();

mw.loader.using( [ 'ext.riskutils' ], function () {
    // Create an observer to watch for removals and clean up pagestate
    const observer = new MutationObserver( function ( mutations ) {

        // Use a Set to collect unique keys from all removed elements in this batch
        const allKeysToDelete = new Set();

        mutations.forEach( function ( mutation ) {
            // Check if any nodes were removed
            mutation.removedNodes.forEach( function ( node ) {
                // We only care about element nodes
                if ( node.nodeType !== 1 ) {
                    return;
                }

                // Check if the removed node itself, or any of its children,
                // has the 'managed-pagestate-keys' data attribute.
                const $node = $( node );
                const $managedElements = $node.find( '[data-managed-pagestate-keys]' )
                    .add( $node.filter( '[data-managed-pagestate-keys]' ) );

                // Accumulate all keys into the Set
                $managedElements.each( function () {
                    const $el = $( this );

                    const keysString = $el.attr( 'data-managed-pagestate-keys' );
                    if ( keysString ) {
                        try {
                            const managedKeys = JSON.parse( keysString ); // managedKeys is an array
                            if ( managedKeys && managedKeys.length > 0 ) {
                                managedKeys.forEach( key => allKeysToDelete.add( key ) );
                            }
                        } catch ( e ) {
                            console.error( 'Observer: Failed to parse managed-pagestate-keys', e );
                        }
                    }
                } );
            } );
        } );

        // After checking all mutations, make a single delete call if needed
        if ( allKeysToDelete.size > 0 ) {
            const keysArray = Array.from( allKeysToDelete );

            // Call deletePageStates ONCE with all unique keys
            RT.pagestate.deletePageStates( keysArray );
        }
    } );

    // Start observing the main wiki content area for changes
    const targetNode = document.getElementById( 'mw-content-text' ) || document.body;
    observer.observe( targetNode, {
        childList: true, // Watch for nodes being added or removed
        subtree: true    // Watch all descendants of the target
    } );
} );
