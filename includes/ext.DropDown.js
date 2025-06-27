mw.loader.using(['oojs-ui', 'ext.cookie'], function () {
    // Now OOUI is loaded and we can use it

    // Create an OOUI dropdown
    function createDropDown(){
            let cookieName = "DropDown";
	    let dd = new OO.ui.DropdownWidget( {
			label: 'DropDown',
			// The menu is composed within the DropdownWidget
			menu: {
				items: [
					new OO.ui.MenuOptionWidget( {
						data: 'person',
						label: 'person',
					} ),
					new OO.ui.MenuOptionWidget( {
						data: 'man',
						label: 'man',
					} ),
					new OO.ui.MenuOptionWidget( {
						data: 'woman',
						label: 'woman'
					} )
				]
			}
		} );
	
		// select the option stored in cookie, or default
		if (RT.cookie.getCookie(cookieName)){
			dd.getMenu().selectItemByData(
				RT.cookie.getCookie(cookieName)
			);
		}
		// custom css for sizing
		dd.$element.css({'width': '100px'});
		// gender event handler
		dd.getMenu().on('select', function (item) {
			document.cookie = cookieName + item.getData() + 
				";expires=Thu, 5 March 2030 12:00:00 UTC; path=/";
		});
		return dd;
    }

    let w = $('.DropDown');
    alert(w.data('loadfrom'));
    w.replaceWith(createDropDown().$element);
});

