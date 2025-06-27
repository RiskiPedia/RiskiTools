<?php

class RiskiToolsHooks {

    public static function onParserFirstCallInit( Parser &$parser ) {
        $parser->setFunctionHook( 'userinputs', [ self::class, 'renderUserInputs' ] );
        $parser->setFunctionHook( 'fetchdata', [ self::class, 'renderFetchData']);
        $parser->setFunctionHook( 'DropDown', [ self::class, 'renderDropDown']);

        return true;
    }

    public static function renderUserInputs( Parser &$parser ) {
	    $parser->getOutput()->addModules( ['ext.userInfoInput'] );
	    $output = "<span class=\"userInfo\">Loading...</span>\n----";
        return [ $output, 'noparse' => true, 'isHTML' => false ];
    }

    public static function renderFetchData( Parser &$parser, $param1="", $param2="", $param3="", $param4="" ) { 
        $parser->getOutput()->addModules( ['ext.fetchData'] );
        $output = "<span class=\"fetchData $param1 $param2 $param3 $param4\"></span>";
        return [ $output, 'noparse' => true, 'isHTML' => false ];
    }

    /**
     * Converts an array of values in form [0] => "name=value"
     * into a real associative array in form [name] => value
     * If no = is provided, true is assumed like this: [name] => true
     *
     * @param array string $options
     * @return array $results
     */
     public static function extractOptions( array $options ) {
	$results = [];
	foreach ( $options as $option ) {
		$pair = array_map( 'trim', explode( '=', $option, 2 ) );
		if ( count( $pair ) === 2 ) {
			$results[ $pair[0] ] = $pair[1];
		}
		if ( count( $pair ) === 1 ) {
			$results[ $pair[0] ] = true;
		}
	}
	return $results;
    }

    /**
     * Create a drop-down select box from some data stored in a DataTable2 table.
     *
     * Use it like this:
     *
     * {{#DropDown:title=...|table=...|text_column=...|value_column=...}}
     * ... where:
     * title: will be the title of the drop-down box
     * table: the DataTable2 table that defines the options (REQUIRED)
     * text_column: the column name for the displayed values (optional, first column by default)
     * value_column: the column name for the resulting values (optional, second column by default OR first if it is a one-column table)
     *
     */
    public static function renderDropDown( Parser &$parser) {
        $parser->getOutput()->addModules( ['ext.DropDown'] );

	$options = RiskiToolsHooks::extractOptions( array_slice( func_get_args(), 1 ) );

	if (!array_key_exists('title', $options)) {
	    $options['title'] = "Select";
        }
	
	/* Just put a placeholder <span> in the page; it gets replaced by the JavaScript in ext.DropDown.js */
        $output = "<span class=\"DropDown\" data-loadfrom=\"foo\">Loading...</span>";

	return [ $output,  'noparse' => true, 'isHTML' => false ];
    }
}
