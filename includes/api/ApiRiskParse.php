<?php

use MediaWiki\MediaWikiServices;

class ApiRiskParse extends ApiBase {

    public function execute() {
        // Get parameters from the JS request
        $params = $this->extractRequestParams();
        $requestsJSON = $params['requests'];
        $pageTitleStr = $params['title'];

        $requests = json_decode( $requestsJSON, true );
        if ( !is_array( $requests ) ) {
            $this->dieWithError( 'Invalid requests data. Must be a JSON object.', 'invalidrequests' );
        }

        // Generate a secure, random marker for this batch
        $uniquetext = bin2hex( random_bytes( 16 ) );

        // Get the Title object for context
        $title = Title::newFromText( $pageTitleStr );
        if ( !$title || !$title->exists() ) {
            $this->dieWithError( 'Invalid page title provided.', 'invalidtitle' );
        }

        // Get the parser
        $options = ParserOptions::newFromContext( $this->getContext() );

        $results = []; // This will hold our { id: html } results

        foreach ( $requests as $id => $requestData ) {
            if (!isset($requestData['text']) || !isset($requestData['params']) || !isset($requestData['pagestate'])) {
                $results[$id] = '<span class="error">Error: Invalid request data from client.</span>';
                continue;
            }

            $wikitext = $requestData['text'];
            $paramMap = $requestData['params'];     // Already sorted [name => expression]
            $pagestate = $requestData['pagestate']; // [key => value]

            // Build the {pagestate} variable string
            $pageStatePairs = [];
            foreach ($pagestate as $key => $value) {
                $escapedKey = self::escapeForTemplate($key);
                $escapedValue = self::escapeForTemplate($value);
                $pageStatePairs[] = "$escapedKey=$escapedValue";
            }
            // Add the special 'pagestate' key to the array for substitution
            $pagestate['pagestate'] = implode('|', $pageStatePairs);

            $resolvedParams = [];

            // 1. Resolve all parameters in their sorted order
            foreach ($paramMap as $name => $expression) {
                $currentExpr = $expression;

                // Substitute already-resolved internal parameters
                foreach ($resolvedParams as $key => $value) {
                    $currentExpr = str_replace('{'.$key.'}', $value, $currentExpr);
                }

                // Substitute external pagestate parameters (including the new 'pagestate' key)
                foreach ($pagestate as $key => $value) {
                    $currentExpr = str_replace('{'.$key.'}', $value, $currentExpr);
                }

                // --- Correct Parsing Logic ---
                // Wrap the expression in markers to get the raw HTML content,
                // preserving any formatting (e.g., <b> tags).
                $wrappedExpr = $uniquetext . $currentExpr . $uniquetext;

                // Parse as inline content (true, false) to avoid extra <p> wrappers
                $exprParser = MediaWikiServices::getInstance()->getParser();
                $exprOutput = $exprParser->parse( $wrappedExpr, $title, $options, true, true );

                // Get the full HTML output
                $fullHtml = $exprOutput->getText( [ 'unwrap' => false ] );

                // Use stripMarkers to get the clean, unwrapped HTML content
                $resolvedValue = self::stripMarkers( $fullHtml, $uniquetext );

                $resolvedParams[$name] = $resolvedValue;
            }

            // 2. Now, build the final wikitext
            $finalWikitext = $wikitext;

            // Substitute the newly resolved internal parameters
            foreach ($resolvedParams as $key => $value) {
                $finalWikitext = str_replace('{'.$key.'}', $value, $finalWikitext);
            }
            // And any external pagestate values the main text might use
            foreach ($pagestate as $key => $value) {
                $finalWikitext = str_replace('{'.$key.'}', $value, $finalWikitext);
            }

            // 3. Parse the final wikitext into HTML
            // Wrap the text in markers, just like the 'parse' action does
            $wrappedText = $uniquetext . $finalWikitext . $uniquetext;

            // Parse the wikitext
            $finalParser = MediaWikiServices::getInstance()->getParser();
            $parserOutput = $finalParser->parse( $wrappedText, $title, $options, true, true );
            $fullHtml = $parserOutput->getText( [ 'unwrap' => false ] );

            // Strip the wrapper HTML and the markers
            $results[$id] = self::stripMarkers( $fullHtml, $uniquetext );
        }

        // Add the results to the API output
        $this->getResult()->addValue( null, $this->getModuleName(), [
                                        'results' => $results,
        ] );

        return true;
    }

    /**
     * Replicates the client-side escapeForTemplate to make strings safe
     * for MediaWiki template parameter syntax.
     * Escapes characters that aren't on a simple whitelist.
     */
    private static function escapeForTemplate($str) {
        // Use preg_replace_callback to find all chars not in the whitelist
        return preg_replace_callback(
            '/[^a-zA-Z0-9_+,.!@#$%^*:;\\- ]/u', // Whitelist of safe chars
            function ($matches) {
                // Get the UTF-8 decimal code point for the character
                $ord = mb_ord($matches[0], 'UTF-8');
                return '&#' . $ord . ';';
            },
            (string)$str // Cast to string to be safe
        );
    }

    /**
     * Helper to strip the outer <p> and <div> tags and the unique markers.
     */
    private static function stripMarkers( $html, $marker ) {
        $startIndex = strpos( $html, $marker );
        if ( $startIndex === false ) {
            return $html; // Marker not found
        }
        $startIndex += strlen( $marker );

        $endIndex = strrpos( $html, $marker );
        if ( $endIndex === false || $endIndex <= $startIndex ) {
            return $html; // End marker not found or in wrong place
        }

        return substr( $html, $startIndex, $endIndex - $startIndex );
    }

    public function getAllowedParams() {
        return [
            'requests' => [
                ApiBase::PARAM_TYPE => 'string',
                ApiBase::PARAM_REQUIRED => true,
            ],
            'title' => [
                ApiBase::PARAM_TYPE => 'string',
                ApiBase::PARAM_REQUIRED => true,
            ],
        ];
    }

    public function needsToken() {
        return 'csrf'; // Use 'csrf' token to prevent CSRF attacks
    }
}