const utils = {
    hasCookie: function (name) {
        const riskiData = mw.config.get('riskiData') || {};
        return name in riskiData; // Returns true if name exists, false otherwise
    },
    getCookie: function (name) {
        const riskiData = mw.config.get('riskiData') || {};
        if (!(name in riskiData)) {
            throw new Error(`Cookie "${name}" is not set`);
        }
        return riskiData[name];
    },
    setCookie: function (name, value) {
       if (typeof name !== 'string' || name.trim() === '' || value === undefined) {
            console.error('Invalid name or value:', name, value);
            return;
        }

        let riskiData = mw.config.get('riskiData') || {}
        riskiData[name] = value;
        mw.config.set('riskiData', riskiData);

        var updates = {};
        updates[name]=value;

        new mw.Api().postWithToken('csrf', {
            action: 'updateriskidata',
            updates: JSON.stringify(updates)
        }).done(function(data) {
            if (data.success) {
                console.log('Updated:', pairs);
            }
        }).fail(function(err) {
            console.error('Update failed:', err);
            if (err.error) {
                console.log('Error details:', err.error);
            }
        });
    }
}

window.RT = window.RT || {};
window.RT.cookie = utils;
