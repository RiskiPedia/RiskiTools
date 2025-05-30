const utils = {
    getCookie: function (name) {
        let cookie = `; ${document.cookie}`;
        let parts = cookie.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        else return -1;
    },

    setCookie: function (name, value) {
        document.cookie = name + '=' + value +
                    ";expires=Thu, 5 March 2030 12:00:00 UTC; path=/";
    },

    deleteAllCookies: function (){
        document.cookie = "userAge=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
        document.cookie = "userGender=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
        document.cookie = "userCountry=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
    },

    deleteCookie: function (name){
        document.cookie = name + "=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
    }
}

window.RT = window.RT || {};
window.RT.cookie = utils;