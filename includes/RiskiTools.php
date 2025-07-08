<?php
require_once __DIR__ . '/autoload.php';

class RiskiToolsHooks {
    /**
     * Registers parser function and tag hooks for RiskiTools.
     * @param Parser $parser The MediaWiki parser instance.
     * @return bool True on success.
     */
    public static function onParserFirstCallInit(Parser &$parser) {
        $parser->setFunctionHook('userinputs', [self::class, 'renderUserInputs']);
        $parser->setFunctionHook('fetchdata', [self::class, 'renderFetchData']);
        $parser->setHook('dropdown', [self::class, 'renderDropDown']);
        $parser->setHook('riskmodel', [self::class, 'renderRiskModel']);
        return true;
    }

    /**
     * Renders a user input placeholder that loads client-side via JavaScript.
     * @param Parser $parser The MediaWiki parser instance.
     * @return array Output array with wikitext and parsing options.
     */
    public static function renderUserInputs(Parser &$parser) {
        $parser->getOutput()->addModules(['ext.userInfoInput']);
        $output = self::generateSpanOutput('userInfo', 'Loading...') . "\n----";
        return [$output, 'noparse' => true, 'isHTML' => false];
    }

    /**
     * Renders a data-fetching placeholder with dynamic classes.
     * @param Parser $parser The MediaWiki parser instance.
     * @param string $param1 First class parameter.
     * @param string $param2 Second class parameter.
     * @param string $param3 Third class parameter.
     * @param string $param4 Fourth class parameter.
     * @return array Output array with wikitext and parsing options.
     */
    public static function renderFetchData(Parser &$parser, $param1 = '', $param2 = '', $param3 = '', $param4 = '') {
        $parser->getOutput()->addModules(['ext.fetchData']);
        $classes = array_filter(['fetchData', $param1, $param2, $param3, $param4]);
        $output = self::generateSpanOutput(implode(' ', $classes), '');
        return [$output, 'noparse' => true, 'isHTML' => false];
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
            return self::formatError('dropdown: empty table');
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
