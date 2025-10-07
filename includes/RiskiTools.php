<?php

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
        $parser->setHook('riskparameter', [self::class, 'renderRiskParameter']);
        $parser->setHook('riskdatalookup', [self::class, 'renderRiskDataLookup']);
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
     * Generates a sanitized HTML div or span element.
     * @param string $class CSS class for the span.
     * @param string $data Content inside the span.
     * @param array $attributes Key-value pairs for HTML attributes.
     * @param array $extraAttrs Additional attributes without values.
     * @return string HTML span element.
     */
    private static function generateDivOrSpan($tag, $class, $data, $attributes = [], $extraAttrs = []) {
        $attrString = '';
        foreach ($attributes as $key => $value) {
            $attrString .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
        }
        foreach ($extraAttrs as $key => $value) {
            $attrString .= ' ' . htmlspecialchars($key) . ($value ? '="' . htmlspecialchars($value) . '"' : '');
        }
        $class = htmlspecialchars($class);
        return "<$tag class=\"$class\" $attrString>$data</$tag>";
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
     * Make referring to a RiskData table by name convenient:
     * If a fully-qualified name is not given, automatically look
     * for the table on the current page OR a Data/ subpage.
     * Returns null if tableName can't be found, otherwise returns
     * a Title object that is the fully-qualified name.
     */
    public static function fullyResolveDT2Title($tableName, $pageTitle) {
        $dt2db = RiskData::singleton()->getDatabase();

        foreach ([$tableName, "$pageTitle$tableName", "$pageTitle:$tableName", "$pageTitle/Data:$tableName"] as $t) {
            $fqTable = RiskDataParser::table2title($t);
            if ($dt2db->getColumns($fqTable->getDBkey())) {
                return $fqTable;
            }
        }
        return null;
    }

    /**
     * Renders a dropdown from a RiskData table using a <dropdown> tag.
     * @param string $content Inner content of the tag (unused).
     * @param array $attribs Tag attributes (e.g., ['table' => '...', 'title' => '...']).
     * @param Parser $parser The MediaWiki parser instance.
     * @param PPFrame $frame The preprocessor frame.
     * @return string Output wikitext.
     * @throws MWException If RiskData is not loaded.
     */
    public static function renderDropDown($content, array $attribs, Parser $parser, PPFrame $frame) {
        if (!ExtensionRegistry::getInstance()->isLoaded('RiskData')) {
            throw new MWException('RiskData extension is required but not loaded.');
        }
        $dt2 = RiskData::singleton();

        $parserOutput = $parser->getOutput();
        $parserOutput->addModules(['ext.dropdown']);
        
        $options = self::processTagAttributes($attribs);
        if (!isset($options['table'])) {
            return self::formatError('dropdown: missing table attribute');
        }
        
        $table = self::fullyResolveDT2Title($options['table'], $parser->getTitle()->getPrefixedText());
        if ($table === null) {
            return self::formatError('dropdown: cannot find RiskData table ' . htmlspecialchars($options['table']));
        }

        $title = $options['title'] ?? 'Select';
        $alldata = $dt2->getDatabase()->select($table, null, false, $pages, __METHOD__);
        if (count($alldata) < 1) {
            return self::formatError('dropdown: empty RiskData table ' . htmlspecialchars($table));
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
            'data-choiceshex' => bin2hex($data)
        ];
        if (isset($options['default'])) { $attributes['data-default'] = $options['default']; }
        if (isset($options['default-index'])) { $attributes['data-default-index'] = $options['default-index']; }
        $output = self::generateDivOrSpan('span', 'DropDown', '', $attributes); // , ['hidden' => '']);

        return $output;
    }

    /**
     * Update the riskitools_riskmodel database when a page containing a <riskmodel> tag is
     * changed.
     * AND if the edit is to a sub-page, purge the parent page cache (so changes to
     * a /Data subpage are reflected in the parent page immediately).
     *
     * Called when a revision was inserted due to an edit, file upload, import or page move.
     */
    public static function onRevisionFromEditComplete( $wikiPage, $rev, $originalRevId, $user, &$tags ) {
        $content = $rev->getContent( SlotRecord::MAIN )->getWikitextForTransclusion();
        $db = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
        $pageId = $wikiPage->getId();

        /* Ignore RiskModel tags inside <nowiki>...</nowiki> */
        $content = preg_replace('/<nowiki>.*?<\/nowiki>/is', '', $content);

        /* Grab all the <riskmodel> tags on the page */
        Parser::extractTagsAndParams( [ 'riskmodel' ], $content, $riskmodels );
        /* Delete old data */
        $db->delete( 'riskitools_riskmodel', [ 'rm_page_id' => $pageId ], __METHOD__ );

        /* RiskModel names have to be unique on a page */
        $seenNames = [];

        /* Insert new data */
        foreach ($riskmodels as $riskmodel) {
            [ $element, $content, $args ] = $riskmodel;
            $options = self::processTagAttributes($args);

            $name = $args['name'] ?? '';

            if (isset($seenNames[$name])) { continue; }
            $seenNames[$name] = true;

            $db->insert( 'riskitools_riskmodel',
                [ 'rm_page_id' => $pageId,
                  'rm_text' => $content ?? '',
                  'rm_name' => $name
                ]);
        }

        // Get the title of the edited page
        $title = $wikiPage->getTitle();
        $titleText = $title->getPrefixedText();

        // Check if the page is a subpage, and if it is, purge parent page's cache:
        if ( strpos( $titleText, '/' ) !== false ) {
            $parentTitleText = preg_replace( '/\/[^\/]+$/', '', $titleText );
            $parentTitle = Title::newFromText( $parentTitleText );
            if ( $parentTitle && $parentTitle->exists() ) {
                $wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $parentTitle );
                $wikiPage->doPurge();
            }
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
        $parserOutput = $parser->getOutput();
        $options = self::processTagAttributes($attribs);
        
        if (!isset($options['name'])) {
            return self::formatError('riskmodel: missing name attribute');
        }

        $pageTitle = $parser->getTitle()->getFullText();
        $fullRiskModelTitle = $pageTitle . ':' . $options['name'];

        // TODO:
        // Output GUI widgets that let risk model creators
        // tweak inputs and observe the calculation output
        $content = htmlspecialchars($content);
        $output = <<<END
<pre>
  RiskModel: $fullRiskModelTitle
    Content: $content
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
     * Make referring to a RiskModel by name convenient:
     * If a fully-qualified name is not given, automatically look
     * for the model on the current page OR a Data/ subpage.
     * Returns null if modelName can't be found, otherwise returns
     * the riskModel data (row from the database)
     */
    public static function fetchRiskModel($modelName, $pageTitle) {
        $db = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
        foreach ([$modelName, "$pageTitle:$modelName", "$pageTitle/Data:$modelName"] as $t) {

            list($pt, $mn) = self::splitAtLastColon($t);
            if (!$mn) { continue; }
            $title = Title::newFromText($pt);
            if (!$title->exists()) { continue; }
            $pageId = $title->getArticleID();

            $result = $db->select(
                'riskitools_riskmodel',
                ['rm_text'],
                ['rm_page_id' => $pageId, 'rm_name' => $mn],
                __METHOD__
                );
            if ($result->numRows() == 0) { continue; }
            return $result->fetchRow();
        }
        return null;
    }

    /**
     * Renders a <RiskDisplay>
     *
     * @param string $content Inner content of the tag (unused).
     * @param array $attribs Tag attributes
     * @param Parser $parser The MediaWiki parser instance.
     * @param PPFrame $frame The preprocessor frame.
     * @return string Output wikitext.
     */
    public static function renderRiskDisplay($content, array $attribs, Parser $parser, PPFrame $frame) {
        $parserOutput = $parser->getOutput();
        $parserOutput->addModules(['ext.riskdisplay']);

        $options = self::processTagAttributes($attribs);
        
        if (isset($options['model'])) {
            $row = self::fetchRiskModel($options['model'], $parser->getTitle()->getPrefixedText());
            if ($row === null) {
                return self::formatError("riskdisplay: can't find riskmodel named ".$options['model']);
            }
            if (!$content || trim($content) == "") {
                $text = $row['rm_text'];
            } else {
                $text = $content;
            }
        } else {
            $text = $content;
        }

        $defaultTextHTML = "";
        if (isset($options['defaulttext'])) {
            $defaultTextHTML = $parser->recursiveTagParse($options['defaulttext'], $frame);
        }
        
        $attributes = [
            // Avoid wiki parsing that seems to happen if $text is not
            // encoded:
            'data-originaltexthex' => bin2hex($text),
            'id' => bin2hex(random_bytes(16))
        ];
        $output = self::generateDivOrSpan("div", "RiskDisplay", $defaultTextHTML, $attributes);

        return $output;
    }

    /**
     * Renders a <RiskParameter>
     *
     * @param string $content Inner content of the tag; key=value (one pair per line)
     * @param array $attribs Tag attributes (unused)
     * @param Parser $parser The MediaWiki parser instance.
     * @param PPFrame $frame The preprocessor frame.
     * @return string Output wikitext.
     */
    public static function renderRiskParameter($content, array $attribs, Parser $parser, PPFrame $frame) {
        $parserOutput = $parser->getOutput();
        $parserOutput->addModules(['ext.riskparameter']);

        $attributes = [
            'id' => bin2hex(random_bytes(16))
        ];
        $output = self::generateDivOrSpan('span', 'RiskParameter', $content, $attributes, ['hidden' => '']);

        return $output;
    }

    /**
     * Renders a <RiskDataLookup>
     *
     * @param string $content Inner content of the tag; key=value (one pair per line)
     * @param array $attribs Tag attributes (unused)
     * @param Parser $parser The MediaWiki parser instance.
     * @param PPFrame $frame The preprocessor frame.
     * @return string Output wikitext.
     */
    public static function renderRiskDataLookup($content, array $attribs, Parser $parser, PPFrame $frame) {
        $parserOutput = $parser->getOutput();
        $parserOutput->addModules(['ext.riskdatalookup']);
        $dt2 = RiskData::singleton();

        $options = self::processTagAttributes($attribs);
        if (!isset($options['table'])) {
            return self::formatError('riskdatalookup: missing table attribute');
        }
        $table = self::fullyResolveDT2Title($options['table'], $parser->getTitle()->getPrefixedText());
        if ($table === null) {
            return self::formatError('riskdatalookup: cannot find RiskData table ' . htmlspecialchars($options['table']));
        }
        $columns = $dt2->getDatabase()->getColumns($table->getDBkey());

        if (isset($options['row'])) {
            $row = $options['row'];
            $whereclause = $columns[0] . "='" . $options['row'] . "'";
            $data = $dt2->getDatabase()->select($table, $whereclause, false, $pages, __METHOD__);
            if (count($data) < 1) {
                return self::formatError("riskdatalookup: can't find row " . $row . " in RiskData table " . $options['table']);
            }
            $rowdata = $data[0];
        } else {
            $row = intval($options['rowindex'] ?? "0");
            $data = $dt2->getDatabase()->select($table, null, false, $pages, __METHOD__);
            if (count($data) < $row+1) {
                return self::formatError("riskdatalookup: can't find row " . $row . " in RiskData table " . $options['table']);
            }
            $rowdata = $data[$row];
        }
        unset($rowdata['__pageId']);

        $attributes = [
            'id' => bin2hex(random_bytes(16)),
            'data-paramshex' => bin2hex(json_encode($rowdata))
        ];
        $output = self::generateDivOrSpan('span', 'RiskDataLookup', $content, $attributes, ['hidden' => '']);

        return $output;
    }
}
