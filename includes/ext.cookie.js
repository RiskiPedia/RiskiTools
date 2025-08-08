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
        // TODO: Update session on server...
    },

    deleteAllCookies: function (){
        mw.config.set('riskiData', {});
    },

    deleteCookie: function (name){
        let riskiData = mw.config.get('riskiData') || {}
        delete riskiData[name];
        mw.config.set('riskiData', riskiData);
    }
}

window.RT = window.RT || {};
window.RT.cookie = utils;
