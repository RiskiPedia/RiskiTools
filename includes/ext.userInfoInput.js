mw.loader.using(['oojs-ui', 'ext.cookie', 'ext.fetchData'], function () {
    // Now OOUI is loaded and we can use it
    // Create text input for age
    // load mediawiki api (will be used to generate list of countries)
    var api = new mw.Api();
    let age;
    if ( RT.cookie.getCookie('userAge') ){
    	age = RT.cookie.getCookie('userAge');
    } else {
    	age = "age";
    }
    // age input
    function createAge(){
	    let ageInput = new OO.ui.TextInputWidget( {
	    	placeholder: age
	    });
	    // custom css for sizing
	    ageInput.$element.css({	'width': '60px'});
	    // age input event handler
		ageInput.on('change', function (input) {
			document.cookie = 'userAge=' + input +
				";expires=Thu, 5 March 2030 12:00:00 UTC; path=/";
			RT.fetchData();
		});
	    return ageInput;
	}
    
    // Create an OOUI dropdown for gender selection
    function createGender(){
	    let genderSelect = new OO.ui.DropdownWidget( {
			label: 'Gender',
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
		if (RT.cookie.getCookie("userGender")){
			genderSelect.getMenu().selectItemByData(
				RT.cookie.getCookie("userGender")
			);
		}
		// custom css for sizing
		genderSelect.$element.css({'width': '100px'});
		// gender event handler
		genderSelect.getMenu().on('select', function (item) {
			document.cookie = "userGender=" + item.getData() + 
				";expires=Thu, 5 March 2030 12:00:00 UTC; path=/";
		});
		return genderSelect;
    }

    function createCountry() {
		// Combo box for country input
		let countryInput = new OO.ui.ComboBoxInputWidget( {
			menu: {
				filterFromInput: true,
			}
		} );
		
		// set placeholder
		if (RT.cookie.getCookie('userCountry')){
			countryInput.getInput().$input.attr( 
				'placeholder', RT.cookie.getCookie('userCountry') );
		} else {
			countryInput.getInput().$input.attr( 
				'placeholder', 'Country' );
		}
		
		// api call to get country data
		api.get( {
	        action: 'query',
	        prop: 'revisions',
	        titles: 'list of countries with data',
	        rvprop: 'content',
	        format: 'json'
	    } ).done( function ( data ) {
	    	// api call returns multiple pages, we want the first
	    	let page = data.query.pages[Object.keys( data.query.pages )[0]];
	    	// get the wikitext out of the page
	    	let wikitext = page.revisions[0]['*'];
	    	// get the country list as an array of strings
	    	let list = wikitext.split('\n')[2].split(",");
	    	// add each of those as menu options
	    	for (let element of list){
	    		countryInput.menu.addItems([
	    			new OO.ui.MenuOptionWidget( 
	    				{data: element.trim(), label: element.trim()}
	    			)]);
	    		}
	    });
	    
	    // custom css for sizing
	    countryInput.$element.css({'width':'230px'});
	    
	    // country input event handler
		countryInput.getMenu().on('select', function (item) {
			document.cookie = "userCountry=" + item.getData() + 
				";expires=Thu, 5 March 2030 12:00:00 UTC; path=/";
		});
		
	    return countryInput;
    }
    
		
    // Delete cookies button stuff
	let delCookies = new OO.ui.ButtonWidget( {
		label: "Delete Cookies"
	});
	
	// set font weight to normal, default is bold
	delCookies.$element.find(
		'.oo-ui-labelElement-label').css('font-weight', 'normal');
		
	// delete cookies handler
	delCookies.on('click', function (){
		RT.cookie.deleteAllCookies();
		// set the icon to a checkmark
		delCookies.setIcon('check');
		refreshFields();
		RT.fetchData();
	}); 
	
	// info button stuff
	let delCookiesInfo = new OO.ui.FieldLayout( 
		delCookies, {
		align: 'inline',
		help: `We use temporary cookies to display accurate risk data. 
			This info is never saved or shared`
	} );
    
    delCookiesInfo.$element.find(
		'oo-ui-iconElement-icon').css('color', 'gray');
    
	// function to create fields, will be usd again when deleting cookies
	function refreshFields() {
    	$('.userInfo').html(createInputField().$element);
    }
    
    // create input field to house all the inputs
    function createInputField(){
		return new OO.ui.HorizontalLayout({
			items: [createAge(), createGender(), 
			createCountry(), delCookiesInfo],
	    	align: 'top'
		});
    }
	
    refreshFields();
    $('.genderSelect').replaceWith(createGender().$element);
    $('.ageInput').replaceWith(createAge().$element);
    $('.countryInput').replaceWith(createCountry().$element);
    $('.deleteCookies').replaceWith(delCookiesInfo.$element);
	RT.fetchData();
});


