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
        if ( !ExtensionRegistry::getInstance()->isLoaded( 'DataTable2' ) ) {
    	    throw new MWException( 'DataTable2 extension is required but not loaded.' );
	}
	$dt2 = DataTable2::singleton();

        $parser->getOutput()->addModules( ['ext.DropDown'] );

	$options = RiskiToolsHooks::extractOptions( array_slice( func_get_args(), 1 ) );

	if (!isset($options['table'])) {
	    return [ 'DropDown: missing table= argument' ];
        }
	$table = DataTable2Parser::table2title( $options['table'] );
	$title = $options['title'] ?? 'Select';

	$alldata = $dt2->getDatabase()->select($table, null, false, $pages, __METHOD__);
	if (count($alldata) < 1) {
	   return [ 'DropDown: empty table' ];
	}
	$column_names = array_keys($alldata[0]);
	$text_column = $options['text_column'] ?? $column_names[0];
	$value_column = $options['value_column'] ?? $column_names[1] ?? $text_column;

	/* $alldata is all the data in the table. We just want two columns, the text_column and value_column, so: */
	$data = array_combine(array_column($alldata, $text_column), array_column($alldata, $value_column));

	/* Just put a placeholder <span> in the page; it gets replaced by the JavaScript in ext.DropDown.js */
        $output = "<span class=\"DropDown\" data-title=\"$title\">".json_encode($data)."</span>";
	$output .= "<pre>".json_encode($data)."</pre>";

	return [ $output,  'noparse' => true, 'isHTML' => false ];
    }
}
