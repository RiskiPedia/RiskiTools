const utils = {
    getCookie: function (name) {
        let riskiData = mw.config.get('riskiData') || {}
        if (name in riskiData) return riskiData[name];
        else return -1;
    },

    setCookie: function (name, value) {
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
