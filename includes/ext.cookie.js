const utils = {
    hasCookie: function (name) {
        const riskiData = mw.config.get('riskiData') || {};
        return name in riskiData;
    },
    getCookie: function (name) {
        const riskiData = mw.config.get('riskiData') || {};
        if (!(name in riskiData)) {
            throw new Error(`Cookie "${name}" is not set`);
        }
        return riskiData[name];
    },
    setCookies: function (nameValuePairs) {
        if (!nameValuePairs || typeof nameValuePairs !== 'object') {
            console.error('RT.setCookies: Invalid nameValuePairs:', nameValuePairs);
            return;
        }

        const riskiData = mw.config.get('riskiData') || {};
        const updates = {};

        // Validate and collect updates
        for (const [name, value] of Object.entries(nameValuePairs)) {
            if (typeof name !== 'string' || name.trim() === '' || value === undefined) {
                console.error('RT.setCookies: Invalid name or value:', name, value);
                continue;
            }
            riskiData[name] = value;
            updates[name] = value;
        }

        // Skip if no valid updates
        if (Object.keys(updates).length === 0) {
            console.warn('RT.setCookies: No valid updates to process');
            return;
        }

        // Update client-side state
        mw.config.set('riskiData', riskiData);

        // Trigger hook once
        mw.hook('riskiData.changed').fire();

        // Send updates to server
        new mw.Api().postWithToken('csrf', {
            action: 'updateriskidata',
            updates: JSON.stringify(updates)
        }).then(function (data) {
            if (data.updateriskidata?.success) {
                console.log('RT.setCookies: Updated:', updates);
            } else {
                console.warn('RT.setCookies: Update succeeded but unexpected response:', data);
            }
        }).catch(function (err) {
            console.error('RT.setCookies: Update failed:', err);
        });
    },
    setCookie: function (name, value) {
        this.setCookies({ [name]: value });
    }
};

window.RT = window.RT || {};
window.RT.cookie = utils;
