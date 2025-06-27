mw.loader.using(['oojs-ui', 'ext.cookie'], function () {
    // Now OOUI is loaded and we can use it

    // Create an OOUI dropdown
    function createDropDown(title, data){
        let cookieName = "DropDown";

	let menuOptions = Object.keys(data).map(key => new OO.ui.MenuOptionWidget({
            label: key,
	    data: data[key]
	}))
	let dd = new OO.ui.DropdownWidget( {
	    label: title,
	    menu: {
		items: menuOptions
	    }
	} );
	
	// select the option stored in cookie, or default
	if (RT.cookie.getCookie(cookieName)){
	    dd.getMenu().selectItemByData(
		RT.cookie.getCookie(cookieName)
	    );
	}
	// Calculate a reasonable size based on text length
	// Calculate width: 10 pixels per character for longest label
	const maxWidth = Math.max(...Object.keys(data).map(key => key.length * 10));
        // Add padding and dropdown icon (approximate)
        const padding = 20; // OOUI padding
        const iconWidth = 20; // Dropdown arrow
        dd.$element.css('width', `${maxWidth + padding + iconWidth}px`);

	// Update cookie when value changes
	dd.getMenu().on('select', function (item) {
	    document.cookie = cookieName + item.getData() + 
		";expires=Thu, 5 March 2030 12:00:00 UTC; path=/";
	});
	return dd;
    }

    let w = $('.DropDown');
    // TODO:  This isn't correct if the selector returns multiple DropDowns....
    const data = JSON.parse(w.text());
    w.replaceWith(createDropDown(w.data('title'), data).$element);
});

