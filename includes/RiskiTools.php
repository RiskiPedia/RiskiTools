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
     * Generates the data attribute for managed page state keys.
     * @param string[] $keys An array of page state key names.
     * @return array An attribute array [ 'data-managed-pagestate-keys' => '["json", "keys"]' ]
     */
    private static function getManagedKeysAttribute(array $keys) {
        // Use array_values to ensure it's always a JSON array [] not an object {}
        return ['data-managed-pagestate-keys' => json_encode(array_values($keys))];
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
     * Finds all {placeholder} variables in a string, restricted to letters,
     * numbers, and underscores.
     * Avoids matching MediaWiki templates like {{foo}} or {{{foo}}}.
     *
     * @param string $text The text to search.
     * @return array A list of unique placeholder names (without braces).
     */
    private static function findPlaceholders($text) {
        // Regex:
        // (?<!\{)  - Negative lookbehind: not preceded by {
        // \{         - Literal {
        // ([a-zA-Z0-9_]+) - Capture group 1: letters, numbers, underscore
        // \}         - Literal }
        // (?!\})    - Negative lookahead: not followed by }
        preg_match_all('/(?<!\{)\{([a-zA-Z0-9_]+)\}(?!\})/', $text, $matches);

        // Return only the unique captured names (index 1)
        return array_unique($matches[1]);
    }

    /**
     * Sorts parameters based on their {placeholder} dependencies.
     * @param array $parameters An associative array of ['name' => 'expression'].
     * @return array An array with ['sorted' => [...], 'error' => null] on success,
     * or ['sorted' => null, 'error' => '...'] on failure.
     */
    private static function topologicalSortParameters(array $parameters) {
        $adjList = [];    // $adjList[$node] = [list of nodes that depend on $node]
        $inDegree = [];   // $inDegree[$node] = count of dependencies for $node
        $paramNames = array_keys($parameters);

        // 1. Initialize graph and in-degree for all parameters
        foreach ($paramNames as $name) {
            $adjList[$name] = [];
            $inDegree[$name] = 0;
        }

        // 2. Build the graph and in-degree map
        foreach ($parameters as $name => $expression) {
            $dependencies = self::findPlaceholders($expression);
            foreach ($dependencies as $dep) {
                // Only consider dependencies on other parameters in this set
                if (in_array($dep, $paramNames)) {
                    // $dep is a dependency for $name
                    // Add an edge from $dep -> $name
                    $adjList[$dep][] = $name;
                    $inDegree[$name]++;
                }
            }
        }

        // 3. Initialize the queue with all nodes having an in-degree of 0
        $queue = new \SplQueue();
        foreach ($inDegree as $name => $degree) {
            if ($degree === 0) {
                $queue->enqueue($name);
            }
        }

        // 4. Process the graph
        $sortedList = [];
        while (!$queue->isEmpty()) {
            $node = $queue->dequeue();
            $sortedList[] = $node;

            foreach ($adjList[$node] as $neighbor) {
                // "Remove" the edge from $node to $neighbor
                $inDegree[$neighbor]--;
                if ($inDegree[$neighbor] === 0) {
                    $queue->enqueue($neighbor);
                }
            }
        }

        // 5. Check for cycles
        if (count($sortedList) !== count($paramNames)) {
            // A cycle was detected. Find the nodes involved.
            $problemNodes = array_keys(array_filter(
                $inDegree,
                fn($degree) => $degree > 0
            ));
            $errorMsg = 'Circular reference detected in riskmodel parameters. ' .
                        'Problem parameters: ' . implode(', ', $problemNodes);
            return ['sorted' => null, 'error' => $errorMsg];
        }

        return ['sorted' => $sortedList, 'error' => null];
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
        
        $managedKeys = array_keys($alldata[0]);

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

        $attributes = array_merge($attributes, self::getManagedKeysAttribute($managedKeys));

        $output = self::generateDivOrSpan('span', 'RiskiUI DropDown', '', $attributes);

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

        /* Delete old data (_riskmodel_params are deleted by ON DELETE CASCADE sql constraint). */
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

            // 1. Extract all data- attributes into a parameters array
            $parameters = [];
            foreach ($options as $key => $value) {
                if (strpos($key, 'data-') === 0) {
                    $paramName = substr($key, 5); // Get 'foo' from 'data-foo'
                    $parameters[$paramName] = $value;
                }
            }

            // 2. Topologically sort the parameters
            $sortResult = self::topologicalSortParameters($parameters);

            // 3. Insert the main riskmodel row
            $db->insert( 'riskitools_riskmodel',
                [ 'rm_page_id' => $pageId,
                  'rm_text' => $content ?? '',
                  'rm_name' => $name
                ],
                __METHOD__
            );

            // 4. Get the ID of the row we just inserted
            $modelId = $db->insertId();

            // 5. If sorting was successful and we have a valid ID, insert the parameters
            if ($modelId && $sortResult['error'] === null) {
                $sortedNames = $sortResult['sorted'];
                $order = 0;
                $rows = [];
                foreach ($sortedNames as $paramName) {
                    $rows[] = [
                        'rmp_model_id' => $modelId,
                        'rmp_name' => $paramName,
                        'rmp_expression' => $parameters[$paramName],
                        'rmp_order' => $order
                    ];
                    $order++;
                }

                if (!empty($rows)) {
                    $db->insert( 'riskitools_riskmodel_params', $rows, __METHOD__ );
                }
            }
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
        $updater->addExtensionTable( 'riskitools_riskmodel_params',
            __DIR__ . '/../sql/riskitools_riskmodel_params.sql', true );

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

        // 1. Extract all data- attributes into a parameters array
        $parameters = [];
        foreach ($options as $key => $value) {
            if (strpos($key, 'data-') === 0) {
                $paramName = substr($key, 5); // Get 'foo' from 'data-foo'
                $parameters[$paramName] = $value;
            }
        }

        // 2. Topologically sort the parameters
        $sortResult = self::topologicalSortParameters($parameters);

        // 3. Handle circular reference errors
        if ($sortResult['error']) {
            return self::formatError('riskmodel ' . $options['name'] . ': ' . $sortResult['error']);
        }

        $sortedNames = $sortResult['sorted'];

        // 4. Display the sorted parameters for testing
        $output = "<pre>\n";
        $output .= "RiskModel: " . htmlspecialchars($fullRiskModelTitle) . "\n";

        if (!empty($sortedNames)) {
            $output .= "Sorted Parameters:\n";
            foreach ($sortedNames as $name) {
                $expression = htmlspecialchars($parameters[$name]);
                $output .= "  " . htmlspecialchars($name) . " = " . $expression . "\n";
            }
        }

        $output .= "Content: " . htmlspecialchars($content) . "\n";
        $output .= "</pre>\n";

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
                ['rm_id', 'rm_text'],
                ['rm_page_id' => $pageId, 'rm_name' => $mn],
                __METHOD__
                );
            if ($result->numRows() == 0) { continue; }
            return $result->fetchRow();
        }
        return null;
    }

    /**
     * Fetches all parameters for a given risk model, in their pre-sorted order.
     * @param int $modelId The rm_id of the model.
     * @return array Associative array of [param_name => expression]
     */
    private static function fetchRiskModelParams($modelId) {
        $db = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
        $res = $db->select(
            'riskitools_riskmodel_params',
            ['rmp_name', 'rmp_expression'],
            ['rmp_model_id' => $modelId],
            __METHOD__,
            ['ORDER BY' => 'rmp_order ASC']
        );
        $params = [];
        foreach ($res as $row) {
            $params[$row->rmp_name] = $row->rmp_expression;
        }
        return $params;
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

        $modelContent = $content; // Default to tag content
        $dbParams = [];

        // 1. Extract local data- attributes from the <riskdisplay> tag
        $localParams = [];
        foreach ($options as $key => $value) {
            if (strpos($key, 'data-') === 0) {
                $localParams[substr($key, 5)] = $value;
            }
        }

        // 2. If a model= attribute is specified, fetch its data
        if (isset($options['model'])) {
            $modelRow = self::fetchRiskModel($options['model'], $parser->getTitle()->getPrefixedText());
            if ($modelRow === null) {
                return self::formatError("riskdisplay: can't find riskmodel named " . htmlspecialchars($options['model']));
            }

            // Fetch the model's pre-sorted parameters
            $dbParams = self::fetchRiskModelParams($modelRow['rm_id']);

            // Use model's content *only if* tag content is empty
            if (empty(trim($content))) {
                $modelContent = $modelRow['rm_text'];
            }
            // else: $modelContent remains the tag's inner content
        }

        // 3. Merge parameters: local <riskdisplay> data- attributes override the model's
        $mergedParams = array_merge($dbParams, $localParams);

        // 4. Re-sort the merged parameters to ensure correct dependency order
        $sortResult = self::topologicalSortParameters($mergedParams);

        if ($sortResult['error']) {
            $modelName = $options['model'] ?? '[inline model]';
            return self::formatError('riskdisplay (' . htmlspecialchars($modelName) . '): ' . $sortResult['error']);
        }

        // 5. Build the final, sorted parameter list to send to the client
        $sortedParams = [];
        foreach ($sortResult['sorted'] as $name) {
            $sortedParams[$name] = $mergedParams[$name];
        }

        // 6. Handle the placeholder attribute
        $placeholderHTML = "";
        if (isset($options['placeholder'])) {
            $placeholderHTML = $parser->recursiveTagParse($options['placeholder'], $frame);
        }

        // 7. Generate output tag with all data for the JavaScript
        $attributes = [
            'data-originaltexthex' => bin2hex($modelContent),
            'data-paramshex' => bin2hex(json_encode($sortedParams)),
            'data-placeholderhtmlhex' => bin2hex($placeholderHTML),
            'id' => bin2hex(random_bytes(16))
        ];
        $output = self::generateDivOrSpan("div", "RiskiUI RiskDisplay", $placeholderHTML, $attributes);

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

        $managedKeys = [];
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $pair = array_map('trim', explode('=', $line, 2));
            if (!empty($pair[0])) {
                $managedKeys[] = $pair[0];
            }
        }

        $attributes = [
            'id' => bin2hex(random_bytes(16))
        ];

        $attributes = array_merge($attributes, self::getManagedKeysAttribute($managedKeys));

        $output = self::generateDivOrSpan('span', 'RiskiUI RiskParameter', $content, $attributes, ['hidden' => '']);

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

        $managedKeys = array_keys($rowdata);

        $attributes = [
            'id' => bin2hex(random_bytes(16)),
            'data-paramshex' => bin2hex(json_encode($rowdata))
        ];

        $attributes = array_merge($attributes, self::getManagedKeysAttribute($managedKeys));
        $output = self::generateDivOrSpan('span', 'RiskiUI RiskDataLookup', $content, $attributes, ['hidden' => '']);

        return $output;
    }
}
