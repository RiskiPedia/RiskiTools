<?php
require_once __DIR__ . '/autoload.php';

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;

class RiskiToolsHooks {
    /**
     * Registers parser function and tag hooks for RiskiTools.
     * @param Parser $parser The MediaWiki parser instance.
     * @return bool True on success.
     */
    public static function onParserFirstCallInit(Parser &$parser) {
        $parser->setHook('dropdown', [self::class, 'renderDropDown']);
        $parser->setHook('riskmodel', [self::class, 'renderRiskModel']);
        $parser->setHook('riskdisplay', [self::class, 'renderRiskDisplay']);
        return true;
    }

    /**
     * Converts an array of name=value strings into an associative array.
     * @param array $options Array of option strings.
     * @return array Associative array of options.
     */
    public static function extractOptions(array $options) {
        $results = [];
        foreach ($options as $option) {
            $pair = array_map('trim', explode('=', $option, 2));
            $results[$pair[0]] = count($pair) === 2 ? $pair[1] : true;
        }
        return $results;
    }

    /**
     * Processes tag attributes into an associative array.
     * @param array $attribs Raw tag attributes.
     * @return array Processed attributes.
     */
    private static function processTagAttributes(array $attribs) {
        $results = [];
        foreach ($attribs as $key => $value) {
            $results[strtolower(trim($key))] = trim($value);
        }
        return $results;
    }

    /**
     * Generates a sanitized HTML span element.
     * @param string $class CSS class for the span.
     * @param string $data Content inside the span.
     * @param array $attributes Key-value pairs for HTML attributes.
     * @param array $extraAttrs Additional attributes without values.
     * @return string HTML span element.
     */
    private static function generateSpanOutput($class, $data, $attributes = [], $extraAttrs = []) {
        $attrString = '';
        foreach ($attributes as $key => $value) {
            $attrString .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
        }
        foreach ($extraAttrs as $key => $value) {
            $attrString .= ' ' . htmlspecialchars($key) . ($value ? '="' . htmlspecialchars($value) . '"' : '');
        }
        $class = htmlspecialchars($class);
        return "<span class=\"$class\" $attrString>$data</span>";
    }

    /**
     * Formats an error message in a standard way.
     * @param string $message Error message.
     * @return string Formatted error HTML.
     */
    private static function formatError($message) {
        return '<span class="error">' . htmlspecialchars($message) . '</span>';
    }

    /**
     * Make saved session data available to JavaScript
     */
    public static function onBeforePageDisplay(OutputPage $out, Skin $skin) {
        $request = $out->getRequest();
        $session = $request->getSession();
        if (!$session->isPersistent()) {
            $session->persist();
        }
        $pairs = $session->get('riskiData', []);

        // Add ?deleteAllCookies to the URL to delete all the riskiData cookies:
        if ($request->getVal('deleteAllCookies')) {
            $session->remove('riskiData');
            $pairs = [];
        }
        $dc = $request->getVal('deleteCookie');
        if ($dc && array_key_exists($dc, $pairs)) {
            unset($pairs[$dc]);
            $session->set('riskiData', $pairs);
        }

        // Add to JS as mw.config variable (JSON-safe)
        $out->addJsConfigVars('riskiData', $pairs);

        return true;
    }

    /**
     * Make $columnName the first item in every row of $inputArray
     */
    private static function rearrangeArrayByColumn($inputArray, $columnName) {
        $rearrangedArray = [];

        foreach ($inputArray as $row) {
            $newRow = [$columnName => $row[$columnName]];
            // Add all other columns except the specified one
            foreach ($row as $key => $value) {
                if ($key !== $columnName) {
                    $newRow[$key] = $value;
                }
            }
            $rearrangedArray[] = $newRow;
        }
        return $rearrangedArray;
    }


    /**
     * Renders a dropdown from a DataTable2 table using a <dropdown> tag.
     * @param string $content Inner content of the tag (unused).
     * @param array $attribs Tag attributes (e.g., ['table' => '...', 'title' => '...']).
     * @param Parser $parser The MediaWiki parser instance.
     * @param PPFrame $frame The preprocessor frame.
     * @return string Output wikitext.
     * @throws MWException If DataTable2 is not loaded.
     */
    public static function renderDropDown($content, array $attribs, Parser $parser, PPFrame $frame) {
        if (!ExtensionRegistry::getInstance()->isLoaded('DataTable2')) {
            throw new MWException('DataTable2 extension is required but not loaded.');
        }
        $dt2 = DataTable2::singleton();

        $parserOutput = $parser->getOutput();
        $parserOutput->addModules(['ext.DropDown']);
        
        $options = self::processTagAttributes($attribs);
        if (!isset($options['table'])) {
            return self::formatError('dropdown: missing table attribute');
        }
        
        $table = DataTable2Parser::table2title($options['table']);
        $title = $options['title'] ?? 'Select';
        $alldata = $dt2->getDatabase()->select($table, null, false, $pages, __METHOD__);
        if (count($alldata) < 1) {
            return self::formatError('dropdown: missing/empty DataTable2 table ' . htmlspecialchars($table));
        }
        
        foreach ($alldata as &$item) {
            unset($item['__pageId']);
        }
        
        $column_names = array_keys($alldata[0]);
        $label_column = $options['label_column'] ?? $column_names[0];
        
        if (!in_array($label_column, $column_names)) {
            $errmsg = 'dropdown: no column named ' . htmlspecialchars($label_column);
            $errmsg .= ' (valid columns are: ' . htmlspecialchars(implode(' ', $column_names)) . ')';
            return self::formatError($errmsg);
        }
        // For simplicity, we re-arrange the array so the label column is always first:
        if ($label_column != $column_names[0]) {
            $alldata = self::rearrangeArrayByColumn($alldata, $label_column);
        }
        
        $data = json_encode($alldata);
        
        $attributes = [
            'data-title' => $title,
        ];
        $output = self::generateSpanOutput('DropDown', $data, $attributes, ['hidden' => '']);

        return $output;
    }

    /**
     * Update the riskitools_riskmodel database when a page containing a <riskmodel> tag is
     * changed.
     *
     * Called when a revision was inserted due to an edit, file upload, import or page move.
     */
    public static function onRevisionFromEditComplete( $wikiPage, $rev, $originalRevId, $user, &$tags ) {
        $content = $rev->getContent( SlotRecord::MAIN )->getWikitextForTransclusion();
        $db = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
        $pageId = $wikiPage->getId();

        /* Grab all the <riskmodel> tags on the page */
        Parser::extractTagsAndParams( [ 'riskmodel' ], $content, $riskmodels );
        /* Delete old data */
        $db->delete( 'riskitools_riskmodel', [ 'rm_page_id' => $pageId ], __METHOD__ );

        /* Insert new data */
        foreach ($riskmodels as $riskmodel) {
            [ $element, $content, $args ] = $riskmodel;
            $options = self::processTagAttributes($args);

            $expression = $args['calculation'] ?? '';
            $name = $args['name'] ?? '';

            $db->insert( 'riskitools_riskmodel',
                [ 'rm_page_id' => $pageId,
                  'rm_expression' => $expression,
                  'rm_text' => $content ?? '',
                  'rm_name' => $name
                ]);
        }
    }

    /**
     * Delete riskmodel data from the database when a page is deleted
     */
    public static function onPageDelete( $page, $deleter, $reason, $status, $suppress ) {
        $db = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
        $pageId = $page->getId();
        $db->delete( 'riskitools_riskmodel', [ 'rm_page_id' => $pageId ], __METHOD__ );
        return true;
    }

    /**
     * @brief [LoadExtensionSchemaUpdates]
     * (https://www.mediawiki.org/wiki/Manual:Hooks/LoadExtensionSchemaUpdates)
     * hook.
     *
     * Add the riskitools_riskmodel table to the database
     *
     * @param DatabaseUpdater $updater Object that updates the database.
     *
     * @return bool Always TRUE.
     */
    public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
        $updater->addExtensionTable( 'riskitools_riskmodel',
	    		__DIR__ . '/../sql/riskitools_riskmodel.sql', true );
	return true;
     }

    /**
     * Renders a <RiskModel>
     *
     * @param string $content Inner content of the tag (unused).
     * @param array $attribs Tag attributes (e.g., ['calculation' => 'x+y']).
     * @param Parser $parser The MediaWiki parser instance.
     * @param PPFrame $frame The preprocessor frame.
     * @return string Output wikitext.
     */
    public static function renderRiskModel($content, array $attribs, Parser $parser, PPFrame $frame) {
        require_once 'ExpressionParser.php';

        $parserOutput = $parser->getOutput();
        $options = self::processTagAttributes($attribs);
        
        if (!isset($options['name'])) {
            return self::formatError('riskmodel: missing name attribute');
        }
        if (!isset($options['calculation'])) {
            return self::formatError('riskmodel: missing calculation attribute');
        }
        $expression = $options['calculation'];

        list($jscode, $vars, $errMsg) = convertToJavaScript($expression);
        if ($errMsg) {
            return self::formatError("riskmodel $expression: $errMsg");
        }
        
        $pageTitle = $parser->getTitle()->getFullText();
        $fullRiskModelTitle = $pageTitle . ':' . $options['name'];

        // TODO:
        // Output GUI widgets that let risk model creators
        // tweak inputs and observe the calculation output
        $jscode = htmlspecialchars($jscode);
        $output = <<<END
<pre>
Name: $fullRiskModelTitle
Code: $jscode
</pre> 
END;
        return $output;
    }

    public static function splitAtLastColon($string) {
        $lastColonPos = strrpos($string, ':');
        if ($lastColonPos === false) {
            // No colon found, return the whole string as the first part, empty second part
            return [$string, ''];
        }
        $before = substr($string, 0, $lastColonPos);
        $after = substr($string, $lastColonPos + 1);
        return [$before, $after];
    }

    /**
     * Renders a <RiskDisplay>
     *
     * @param string $content Inner content of the tag (unused).
     * @param array $attribs Tag attributes (e.g., ['calculation' => 'x+y']).
     * @param Parser $parser The MediaWiki parser instance.
     * @param PPFrame $frame The preprocessor frame.
     * @return string Output wikitext.
     */
    public static function renderRiskDisplay($content, array $attribs, Parser $parser, PPFrame $frame) {
        require_once 'ExpressionParser.php';

        $parserOutput = $parser->getOutput();
        $parserOutput->addModules(['ext.RiskDisplay']);

        $options = self::processTagAttributes($attribs);
        
        if (!isset($options['model'])) {
           return self::formatError('riskdisplay: missing model attribute');
        }

        list($pageTitle, $model) = self::splitAtLastColon($options['model']);
        if ($model == "") {
           return self::formatError('riskdisplay: missing model name');
        }

        $title = Title::newFromText($pageTitle);
        if (!$title || !$title->exists()) {
           return self::formatError("riskdisplay: page \"$pageTitle\" does not exist.");
        }
        $pageId = $title->getArticleID();

        $db = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );

        $result = $db->select(
            'riskitools_riskmodel',
            ['rm_expression','rm_text'],
            ['rm_page_id' => $pageId, 'rm_name' => $model],
            __METHOD__
            );
        if ($result->numRows() == 0) {
            return self::formatError("riskdisplay: model \"$model\" not found on page \"$pageTitle\"");
        }
        $row = $result->fetchRow();
        $text = $row['rm_text'];
        $expression = $row['rm_expression'];

        list($jscode, $vars, $errMsg) = convertToJavaScript($expression);
        if ($errMsg) {
            return self::formatError("riskdisplay $expression: $errMsg");
        }


        $attributes = [
            'data-jscode' => $jscode,
            'id' => bin2hex(random_bytes(16))
        ];
        $output = self::generateSpanOutput("RiskDisplay", $text, $attributes,); // ['hidden' => '']);

        return $output;
    }
}
