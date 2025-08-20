//
// Store key/value pairs in state
// associated with a single page.
//
// Use this for transient UI state that
// shouldn't persist across pages or sessions.
//
// Page data will "shadow" persistent cookies.
//
const utils = {
    hasPageState: function (name) {
        const riskiData = window.RT.pagedata || {};
        if (name in riskiData) { return true; }
        return window.RT.cookie.hasCookie(name);
    },
    getPageState: function (name) {
        const riskiData = window.RT.pagedata || {};
        if (!(name in riskiData)) {
            return window.RT.cookie.getCookie(name);
        }
        return riskiData[name];
    },
    setPageStates: function (nameValuePairs) {
        if (!nameValuePairs || typeof nameValuePairs !== 'object') {
            console.error('RT.setPageStates: Invalid nameValuePairs:', nameValuePairs);
            return;
        }

        const riskiData = window.RT.pagedata || {};

        // Validate and collect updates
        for (const [name, value] of Object.entries(nameValuePairs)) {
            if (typeof name !== 'string' || name.trim() === '' || value === undefined) {
                console.error('RT.setPageStates: Invalid name or value:', name, value);
                continue;
            }
            riskiData[name] = value;
        }
        // Trigger hook once
        mw.hook('riskiData.changed').fire();
    },
    setPageState: function (name, value) {
        this.setPageStates({ [name]: value });
    }
};

window.RT = window.RT || {};
window.RT.pagestate = utils;
window.RT.pagedata = window.RT.pagedata || {};
