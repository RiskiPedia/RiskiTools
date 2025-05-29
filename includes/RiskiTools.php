<?php

class RiskiToolsHooks {

    public static function onParserFirstCallInit( Parser $parser ) {
        // Register a parser function
        $parser->setFunctionHook( 'userinputs', [ self::class, 'renderUserInputs' ] );
        $parser->setFunctionHook( 'fetchdata', [ self::class, 'renderFetchData']);
        return true;
    }

    public static function renderUserInputs( Parser $parser ) {
	    $parser->getOutput()->addModules( ['ext.userInfoInput'] );
	    $output = "<span class=\"userInfo\">Loading...</span>\n----";
        return [ $output, 'noparse' => true, 'isHTML' => false ];
    }

    public static function renderFetchData( Parser $parser, $param1="", $param2="", $param3="", $param4="" ) { 
        $parser->getOutput()->addModules( ['ext.fetchData'] );
        $output = "<span class=\"fetchData $param1 $param2 $param3 $param4\"></span>";
        return [ $output, 'noparse' => true, 'isHTML' => false ];
    }
}
