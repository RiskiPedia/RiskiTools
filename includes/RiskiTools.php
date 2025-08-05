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
     * Update the riskitools_riskmodel database when a page containing a <riskmodel> tag is
     * changed.
     *
     * Called when a revision was inserted due to an edit, file upload, import or page move.
     */
    public static function onRevisionFromEditComplete( $wikiPage, $rev, $originalRevId, $user, &$tags ) {
        $content = $rev->getContent( SlotRecord::MAIN )->getWikitextForTransclusion();
        $db = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
        $pageId = $wikiPage->getId();

        /* Grab all the <riskmodel> tags */
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
                  'rm_expression' => $db->addQuotes($expression),
                  'rm_timestamp' => 'CURRENT_TIMESTAMP',
                  'rm_name' => $name
                ]);
        }
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
        $value_column = $options['value_column'] ?? $column_names[1] ?? $label_column;
        $cookie_name = $options['cookie_name'] ?? $value_column;
        
        foreach ([$label_column, $value_column] as $c) {
            if (!in_array($c, $column_names)) {
                $errmsg = 'dropdown: no column named ' . htmlspecialchars($c);
                $errmsg .= ' (valid columns are: ' . htmlspecialchars(implode(' ', $column_names)) . ')';
                return self::formatError($errmsg);
            }
        }
        
        $data = array_combine(array_column($alldata, $label_column), array_column($alldata, $value_column));
        
        $attributes = [
            'data-title' => $title,
            'data-cookie_name' => $cookie_name
        ];
        $output = self::generateSpanOutput('DropDown', json_encode($data), $attributes, ['hidden' => '']);
        return $output;
    }

    /**
     * @brief [LoadExtensionSchemaUpdates]
     * (https://www.mediawiki.org/wiki/Manual:Hooks/LoadExtensionSchemaUpdates)
     * hook.
     *
     * Add the tables used to store DataTable2 data and metadata to
     * the updater process.
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
     * Renders a mathematical expression as JavaScript code from a <riskmodel> tag.
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

        $allowedVariables = [];
        $errMsg = '';
        try {
            $jsCode = convertToJavaScript($expression, $allowedVariables);
        } catch (MathParser\Exceptions\UnknownTokenException $e) {
            $errMsg = 'bad expression ' . $e->getName();
        } catch (MathParser\Exceptions\ParenthesisMismatchException $e) {
            $errMsg = 'mismatched parentheses';
        } catch (Exception $e) {
            $errMsg = $e->getMessage();
        }
        if ($errMsg) {
            return self::formatError("riskmodel $expression: $errMsg");
        }
        
        $output = '<pre>' . htmlspecialchars($jsCode) . '</pre>';
        return $output;
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
        return '<span class="' . htmlspecialchars($class) . '"' . $attrString . '>' . $data . '</span>';
    }

    /**
     * Formats an error message in a standard way.
     * @param string $message Error message.
     * @return string Formatted error HTML.
     */
    private static function formatError($message) {
        return '<span class="error">' . htmlspecialchars($message) . '</span>';
    }
}
