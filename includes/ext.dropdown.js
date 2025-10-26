mw.loader.using(['ext.riskutils', 'oojs-ui', 'ext.pagestate'], function () {
    'use strict';

    /**
     * Creates a single Dropdown widget.
     * This helper now returns both the widget and any initial state
     * that needs to be set.
     */
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

        // This will hold the state this widget wants to set
        let initialStateChanges = null;

        // Helper: programmatically select an item and return its state
        function applySelectionByItem(item) {
            if (!item) return null;
            dd.getMenu().selectItem(item);
            dd.setLabel(item.getLabel());
            // Return the row data, DON'T set pagestate here
            return JSON.parse(item.getData());
        }

        // If the pagestate already has a selection, override defaultValue/Index
        // and use that previous selection:
        const selectionkey = Object.keys(data[0])[0];

        // Tag the widget with metadata
        dd.$element.data('originalTitle', title);
        dd.$element.data('selectionKey', selectionkey);
        dd.$element.data('menuOptions', menuOptions);

        if (RT.pagestate.hasPageState(selectionkey)) {
            const matchByLabel = menuOptions.find(opt => opt.getLabel() === RT.pagestate.getPageState(selectionkey));
            if (matchByLabel) {
                // Update UI and get the state this widget wants to set
                initialStateChanges = applySelectionByItem(matchByLabel);
            }
        }
        // Apply default (index takes precedence if provided and valid)
        else if (typeof defaultIndex === 'number' && menuOptions[defaultIndex]) {
            initialStateChanges = applySelectionByItem(menuOptions[defaultIndex]);
        } else if (defaultValue) {
            const matchByLabel = menuOptions.find(opt => opt.getLabel() === defaultValue);
            if (matchByLabel) {
                initialStateChanges = applySelectionByItem(matchByLabel);
            }
        }

        // Calculate a reasonable size based on text length
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

        // Update pagestate when value changes
        dd.getMenu().on('select', function (item) {
            const row = JSON.parse(item.getData());
            RT.pagestate.setPageStates(row); // This one is NOT silent
        });

        // Manually attach the widget instance so we can find it later
        dd.$element.data('ooui-widget', dd);

        // Return both the widget and its initial state
        return { widget: dd, stateChanges: initialStateChanges };
    }

    /**
     * Finds all .DropDown elements and replaces them with widgets.
     * Accumulates all initial page state changes and sets them once.
     */
    function createDropDowns() {
        const allInitialStateChanges = {}; // Accumulator for all states

        // All the class="DropDown" elements on the page...
        $('.DropDown').each(function(_i, el) {
            const $el = $(el);
            const data = JSON.parse(mw.riskutils.hexToString($el.data('choiceshex')));
            const title = $el.data('title');

            // Default attributes:
            //    data-default="Label text"
            //    data-default-index="2"    (0-based in this example)
            let defaultValue = $el.data('default');
            let defaultIndex = $el.data('default-index');

            // Coerce defaultIndex to a Number if present
            if (defaultIndex !== undefined) {
                defaultIndex = Number(defaultIndex);
                if (!Number.isFinite(defaultIndex)) defaultIndex = undefined;
            }

            // Call the helper to get the widget and its desired state
            const result = createDropDown(title, data, defaultValue, defaultIndex);

            // 1. Replace the placeholder element's content with the real widget
            $el.html(result.widget.$element);
            // 2. Remove the class so we don't process this element again
            $el.removeClass('DropDown');
            // 3. Accumulate the initial state
            if (result.stateChanges) {
                // Object.assign merges the new state into our accumulator
                Object.assign(allInitialStateChanges, result.stateChanges);
            }
        });

        // After the loop, set all initial states at once.
        if (Object.keys(allInitialStateChanges).length > 0) {
            RT.pagestate.setPageStates(allInitialStateChanges);
        }
    }

    function maybeUpdateSelection(changes) {
        // Find all dropdown widgets on the page
        $('.oo-ui-dropdownWidget').each(function() {
            const $widgetEl = $(this);
            const dd = $widgetEl.data('ooui-widget'); // Get the OOUI widget instance

            // Check if it's a DropdownWidget
            if (!dd || typeof dd.getMenu !== 'function') {
                return; // Continue to next element
            }

            // Get the metadata we stored in createDropDown
            const selectionKey = $widgetEl.data('selectionKey');
            const menuOptions = $widgetEl.data('menuOptions');
            const originalTitle = $widgetEl.data('originalTitle');

            // If it's not one of our managed dropdowns, skip it
            if (!selectionKey || !menuOptions || !originalTitle) {
                return; // Continue to next element
            }

            // CASE 1: Page state was UPDATED (changes is an object)
            if (typeof changes === 'object' && !Array.isArray(changes) && changes !== null) {
                // Check if the state change is relevant to THIS dropdown
                if (Object.prototype.hasOwnProperty.call(changes, selectionKey)) {
                    const newValue = changes[selectionKey];
                    const matchByLabel = menuOptions.find(opt => opt.getLabel() === newValue);

                    if (matchByLabel) {
                        // Check if this isn't already the selected item
                        if (dd.getMenu().findSelectedItem() !== matchByLabel) {
                            // Update the UI only
                            dd.getMenu().selectItem(matchByLabel);
                            dd.setLabel(matchByLabel.getLabel());
                        }
                    } else {
                        console.log('dropdown: pagestate for "'+ selectionKey +'" changed to "' + newValue + '", but no matching option was found.');
                    }
                }
            }
            // CASE 2: Page state was DELETED (changes is an array)
            else if (Array.isArray(changes)) {
                // Check if the deleted key is the one this dropdown tracks
                if (changes.includes(selectionKey)) {
                    // Check if an item is currently selected
                    if (dd.getMenu().findSelectedItem() !== null) {
                        // Clear selection and reset label to original title
                        dd.getMenu().selectItem(null);
                        dd.setLabel(originalTitle);
                    }
                }
            }
        });
    }

    // Initial creation of dropdowns
    createDropDowns();

    // Listen for pagestate changes, update selection if changed:
    mw.hook('riskiData.changed').add(maybeUpdateSelection);

    // Listen for new DropDowns dynamically created (e.g., by RiskDisplay)
    mw.hook('riskiUI.changed').add(createDropDowns);
});
