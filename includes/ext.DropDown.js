mw.loader.using(['oojs-ui', 'ext.cookie', 'ext.pagestate'], function () {
    // Now OOUI is loaded and we can use it

    // Create an OOUI dropdown
    function createDropDown(title, cookie_name, data){
	let menuOptions = data.map(row => {
            // Get the first key of the row object
            const firstKey = Object.keys(row)[0];
            // JSON encode the entire row for the data property
            const jsonEncodedRow = JSON.stringify(row);
            // Create a new OO.ui.MenuOptionWidget
            return new OO.ui.MenuOptionWidget({
                label: row[firstKey],
                data: jsonEncodedRow
            });
        });
	let dd = new OO.ui.DropdownWidget( {
	    label: title,
	    menu: {
		items: menuOptions
	    }
	});
	
        // TODO: set initial state if there's a cookie...

	// Calculate a reasonable size based on text length
	// Calculate width: pxPerChar pixels per character for longest label
	// Note: Grok suggested another way to do this by creating a hidden
	// canvas, rendering to it, and then getting the width. That might
	// be better.
	const pxPerChar = 8;
        const maxStringLength = Math.max(...data.map(item => item[Object.keys(item)[0]].length), title.length);
        const maxWidth = maxStringLength * pxPerChar;
        // Add padding and dropdown icon (approximate)
        const padding = 20; // OOUI padding
        const iconWidth = 20; // Dropdown arrow
        dd.$element.css('width', `${maxWidth + padding + iconWidth}px`);

	// Update cookie when value changes
        dd.getMenu().on('select', function (item) {
            row = JSON.parse(item.getData());
            RT.pagestate.setPageStates(row);
	});
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

