mw.loader.using(['ext.riskutils', 'oojs-ui', 'ext.cookie', 'ext.pagestate'], function () {
    'use strict';

    function createDropDown(title, data, defaultValue, defaultIndex) {
	const menuOptions = data.map(row => {
            const firstKey = Object.keys(row)[0];
            // JSON encode the entire row for the data property
            const jsonEncodedRow = JSON.stringify(row);
            return new OO.ui.MenuOptionWidget({
                label: row[firstKey],
                data: jsonEncodedRow
            });
        });
	const dd = new OO.ui.DropdownWidget( {
	    label: title,
	    menu: { items: menuOptions }
	});
	
        // Helper: programmatically select an item and propagate state
        function applySelectionByItem(item) {
            if (!item) return;
            dd.getMenu().selectItem(item);
            dd.setLabel(item.getLabel());
            const row = JSON.parse(item.getData());
            RT.pagestate.setPageStates(row); // behave as if the user selected it
        }

        // Apply default (index takes precedence if provided and valid)
        if (typeof defaultIndex === 'number' && menuOptions[defaultIndex]) {
            applySelectionByItem(menuOptions[defaultIndex]);
        } else if (defaultValue) {
            const matchByLabel = menuOptions.find(opt => opt.getLabel() === defaultValue);
            if (matchByLabel) {
                applySelectionByItem(matchByLabel);
            }
        }

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
            const row = JSON.parse(item.getData());
            RT.pagestate.setPageStates(row);
	});
	return dd;
    }

    function updateDropDowns() {
        // All the class="DropDown" elements on the page...
        $('.DropDown').each(function(_i, el) {
	    const $el = $(el);
	    const data = JSON.parse(mw.riskutils.hexToString($el.data('choiceshex')));
	    const title = $el.data('title');

            // Default attributes:
            //   data-default="Label text"
            //   data-default-index="2"    (0-based in this example)
            let defaultValue = $el.data('default');
            let defaultIndex = $el.data('default-index');

            // Coerce defaultIndex to a Number if present
            if (defaultIndex !== undefined) {
                defaultIndex = Number(defaultIndex);
                if (!Number.isFinite(defaultIndex)) defaultIndex = undefined;
            }
	    $el.replaceWith(createDropDown(title, data, defaultValue, defaultIndex).$element);
        });
    }

    // Initial update
    updateDropDowns();

    // Listen for new DropDowns dynamically created:
    mw.hook('riskiUI.changed').add(updateDropDowns);
});

