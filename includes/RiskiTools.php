<?php

// autoload.php makes the MathParser  code in math-parser available
require_once __DIR__ . '/autoload.php';

class RiskiToolsHooks {

    public static function onParserFirstCallInit( Parser &$parser ) {
        $parser->setFunctionHook( 'userinputs', [ self::class, 'renderUserInputs' ] );
        $parser->setFunctionHook( 'fetchdata', [ self::class, 'renderFetchData']);
        $parser->setFunctionHook( 'DropDown', [ self::class, 'renderDropDown']);
        $parser->setFunctionHook( 'RiskModel', [ self::class, 'renderRiskModel']);

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
     * {{#DropDown:title=...|table=...|label_column=...|value_column=...}}
     * ... where:
     * title: will be the title of the drop-down box
     * table: the DataTable2 table that defines the options (REQUIRED)
     * label_column: the column name for the displayed values (optional, first column by default)
     * value_column: the column name for the resulting values (optional, second column by default OR first if it is a one-column table)
     * cookie_name: the name of the browser cookie to update as values are selected. Defaults to value_column.
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
	    return [ '<span class="error">DropDown: missing table= argument</span>' ];
        }
	$table = DataTable2Parser::table2title( $options['table'] );
	$title = $options['title'] ?? 'Select';

	$alldata = $dt2->getDatabase()->select($table, null, false, $pages, __METHOD__);
	if (count($alldata) < 1) {
	   return [ '<span class="error">DropDown: empty table</span>' ];
	}
	/* We don't care about __pageId, so: */
	foreach ($alldata as &$item) {
	    unset($item['__pageId']);
	}

	$column_names = array_keys($alldata[0]);
	$label_column = $options['label_column'] ?? $column_names[0];
	$value_column = $options['value_column'] ?? $column_names[1] ?? $label_column;
	$cookie_name = $options['cookie_name'] ?? $value_column;
	foreach ([$label_column, $value_column] as $c) {
	   if (!in_array($c, $column_names)) {
              $errmsg = "DropDown: no column named ".$c;
	      $errmsg .= " (valid columns are: ".implode(' ',$column_names).")";
	      return [ '<span class="error">'.$errmsg.'</span>' ];
	   }   
        }

	/* $alldata is all the data in the table. We just want two columns, the label_column and value_column, so: */
	$data = array_combine(array_column($alldata, $label_column), array_column($alldata, $value_column));

	/** Put a <span> in the output with all the data necessary to create the drop-down.
	 * See ext.DropDown.js, which does the work of replacing the span with an OO.ui.DropDown
	 * The labels and values are given as a JSON-encoded array in the text of the <span>
	 * Other attributes of the dropdown (just the title for now) are passed as custom
	 * data- attributes (JQuery has a built-in $(element).data() method that understands
	 * custom attributes with that name pattern).
	 */
	$attributes = "data-title=\"$title\"";
	$attributes .= " data-cookie_name=\"$cookie_name\"";
	
        $output = "<span hidden class=\"DropDown\" $attributes >".json_encode($data)."</span>";
/*	$output .= "<pre>".json_encode($data)."</pre>";  */

	return [ $output,  'noparse' => true, 'isHTML' => false ];
    }

    public static function renderRiskModel( Parser &$parser) {
        require_once 'ExpressionParser.php';

	$options = RiskiToolsHooks::extractOptions( array_slice( func_get_args(), 1 ) );

	if (!isset($options['calculation'])) {
	    return [ '<span class="error">RiskModel: missing calculation= argument</span>' ];
	}
        $expression = $options['calculation'];

        $allowedVariables = []; // means any variable name is OK
	$errMsg = '';
	try {
	    $jsCode = convertToJavaScript($expression, $allowedVariables);
 	} catch (MathParser\Exceptions\UnknownTokenException $e) {
            $errMsg = 'bad expression '. $e->getName();
	} catch (MathParser\Exceptions\ParenthesisMismatchException $e) {
            $errMsg = 'mismatched parentheses';
	} catch (Exception $e) {
            $errMsg = $e->getMessage();
	}
	if ($errMsg) {
	    return [ '<span class="error">RiskModel '.$expression.':'.$errMsg.'</span>' ];
	}
	else {
	    $output = "<pre>".$jsCode."</pre>";
	    return [ $output,  'noparse' => true, 'isHTML' => false ];
	}
    }
}
