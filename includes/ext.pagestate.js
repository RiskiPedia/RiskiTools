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
            mw.hook('riskiData.changed').fire();
        }
    },
    setPageState: function (name, value) {
        this.setPageStates({ [name]: value });
    }
};

window.RT = window.RT || {};
window.RT.pagestate = p;
window.RT.pagedata = window.RT.pagedata || {};
