mw.loader.using(['oojs-ui', 'ext.cookie', 'ext.pagestate'], function () {
    // Now OOUI is loaded and we can use it

    // Create an OOUI dropdown
    function createDropDown(title, cookie_name, data){
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
	
	// select the option stored in cookie
	if (cookie_name && RT.pagestate.hasPageState(cookie_name)){
	    dd.getMenu().selectItemByData(
		RT.pagestate.getPageState(cookie_name)
	    );
	}
	// Calculate a reasonable size based on text length
	// Calculate width: pxPerChar pixels per character for longest label
	// Note: Grok suggested another way to do this by creating a hidden
	// canvas, rendering to it, and then getting the width. That might
	// be better.
	const pxPerChar = 10;
	const maxWidth = Math.max(...Object.keys(data).map(key => key.length * pxPerChar), title.length * pxPerChar);
        // Add padding and dropdown icon (approximate)
        const padding = 20; // OOUI padding
        const iconWidth = 20; // Dropdown arrow
        dd.$element.css('width', `${maxWidth + padding + iconWidth}px`);

	// Update cookie when value changes
	if (cookie_name) {
	    dd.getMenu().on('select', function (item) {
                RT.pagestate.setPageStates({
                    [cookie_name]: item.getData(),
                    [cookie_name + '_label']: item.getLabel()
                });
	    });
	}
	return dd;
    }

    // All the class="DropDown" elements on the page...
    $('.DropDown').each(function(index, element) {
	let e = $(element);
	const data = JSON.parse(e.text());
	const title = e.data('title');
	const cookie_name = e.data('cookie_name');
	e.replaceWith(createDropDown(title, cookie_name, data).$element);
    });
});

