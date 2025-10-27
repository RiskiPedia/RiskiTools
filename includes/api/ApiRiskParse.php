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
        $parser = MediaWikiServices::getInstance()->getParser();
        $options = ParserOptions::newFromContext( $this->getContext() );

        $results = []; // This will hold our { id: html } results

        foreach ( $requests as $id => $wikitext ) {
            // Wrap the text in markers, just like the 'parse' action does
            $wrappedText = $uniquetext . $wikitext . $uniquetext;

            // Parse the wikitext
            $parserOutput = $parser->parse( $wrappedText, $title, $options, true, true );
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
            return $H_html; // End marker not found or in wrong place
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