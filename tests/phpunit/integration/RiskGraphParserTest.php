<?php

/**
 * @covers RiskiToolsHooks::renderRiskGraph
 * @group RiskiTools
 * @group Database
 */
class RiskGraphParserTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		// Manually register parser hooks for testing
		$parser = MediaWiki\MediaWikiServices::getInstance()->getParser();
		RiskiToolsHooks::onParserFirstCallInit( $parser );
	}

	/**
	 * Test that the parser hook is registered by verifying parsing works
	 */
	public function testParserHookRegistered() {
		$wikitext = <<<WIKITEXT
<riskgraph model="Test">
x-axis: test
x-min: 0
x-max: 1
x-step: 1
y-axis: {result}
</riskgraph>
WIKITEXT;
		$output = $this->parseWikitext( $wikitext );

		// If the hook is registered, we should get a graph div, not raw wikitext
		$this->assertStringContainsString( 'class="RiskiUI RiskGraph"', $output, 'riskgraph parser hook should be registered' );
		$this->assertStringNotContainsString( '<riskgraph', $output, 'Tag should be processed, not rendered as-is' );
	}

	/**
	 * Test basic graph tag parsing
	 */
	public function testBasicGraphParsing() {
		$wikitext = <<<WIKITEXT
<riskgraph model="TestModel" type="line">
x-axis: test_param
x-min: 0
x-max: 10
x-step: 1
y-axis: {result}
title: Test Graph
</riskgraph>
WIKITEXT;

		$output = $this->parseWikitext( $wikitext );

		// Should contain a placeholder div for the graph
		$this->assertStringContainsString( 'class="RiskiUI RiskGraph"', $output );

		// Should have loaded the riskgraph module
		$parserOutput = $this->getParserOutput( $wikitext );
		$modules = $parserOutput->getModules();
		$this->assertContains( 'ext.riskgraph', $modules, 'Should load ext.riskgraph module' );
	}

	/**
	 * Test that required attributes are validated
	 */
	public function testMissingModelAttribute() {
		$wikitext = <<<WIKITEXT
<riskgraph type="line">
x-axis: test_param
x-min: 0
x-max: 10
x-step: 1
y-axis: {result}
</riskgraph>
WIKITEXT;

		$output = $this->parseWikitext( $wikitext );

		// Should contain an error message
		$this->assertStringContainsString( 'error', $output );
		$this->assertStringContainsString( 'model', strtolower( $output ) );
	}

	/**
	 * Test that type attribute defaults to 'line'
	 */
	public function testDefaultTypeAttribute() {
		$wikitext = <<<WIKITEXT
<riskgraph model="TestModel">
x-axis: test_param
x-min: 0
x-max: 10
x-step: 1
y-axis: {result}
</riskgraph>
WIKITEXT;

		$output = $this->parseWikitext( $wikitext );

		// Should not error (type is optional, defaults to 'line')
		$this->assertStringNotContainsString( 'class="error"', $output );
	}

	/**
	 * Test that x-axis configuration is parsed
	 */
	public function testXAxisConfiguration() {
		$wikitext = <<<WIKITEXT
<riskgraph model="TestModel" type="line">
x-axis: speed
x-min: 0
x-max: 100
x-step: 5
y-axis: {risk}
</riskgraph>
WIKITEXT;

		$output = $this->parseWikitext( $wikitext );

		// Should contain data attributes for configuration
		$this->assertStringContainsString( 'data-model="TestModel"', $output );
		$this->assertStringContainsString( 'data-swept-param="speed"', $output );
		$this->assertStringContainsString( 'data-x-min="0"', $output );
		$this->assertStringContainsString( 'data-x-max="100"', $output );
		$this->assertStringContainsString( 'data-x-step="5"', $output );
	}

	/**
	 * Test that y-axis expression is captured
	 */
	public function testYAxisExpression() {
		$wikitext = <<<WIKITEXT
<riskgraph model="TestModel" type="line">
x-axis: speed
x-min: 0
x-max: 100
x-step: 10
y-axis: {fatality_risk}
</riskgraph>
WIKITEXT;

		$output = $this->parseWikitext( $wikitext );

		// Should store y-axis expression in data attribute
		$this->assertStringContainsString( 'data-y-axis', $output );
	}

	/**
	 * Test that title and labels are optional
	 */
	public function testOptionalTitleAndLabels() {
		$wikitext = <<<WIKITEXT
<riskgraph model="TestModel">
x-axis: param
x-min: 0
x-max: 10
x-step: 1
y-axis: {result}
title: My Graph Title
x-label: Parameter Value
y-label: Risk Level
</riskgraph>
WIKITEXT;

		$output = $this->parseWikitext( $wikitext );

		// Should contain title and label data
		$this->assertStringContainsString( 'data-title', $output );
		$this->assertStringContainsString( 'data-x-label', $output );
		$this->assertStringContainsString( 'data-y-label', $output );
	}

	/**
	 * Test that missing x-axis configuration throws error
	 */
	public function testMissingXAxisConfig() {
		$wikitext = <<<WIKITEXT
<riskgraph model="TestModel" type="line">
y-axis: {result}
</riskgraph>
WIKITEXT;

		$output = $this->parseWikitext( $wikitext );

		// Should error about missing x-axis configuration
		$this->assertStringContainsString( 'error', $output );
	}

	/**
	 * Test that invalid min/max/step values are caught
	 */
	public function testInvalidNumericValues() {
		$wikitext = <<<WIKITEXT
<riskgraph model="TestModel">
x-axis: param
x-min: not-a-number
x-max: 10
x-step: 1
y-axis: {result}
</riskgraph>
WIKITEXT;

		$output = $this->parseWikitext( $wikitext );

		// Should error about invalid numeric value
		$this->assertStringContainsString( 'error', $output );
	}

	/**
	 * Test that data-* attributes on tag are preserved
	 */
	public function testCustomDataAttributes() {
		$wikitext = <<<WIKITEXT
<riskgraph model="TestModel" data-age="30" data-gender="male">
x-axis: param
x-min: 0
x-max: 10
x-step: 1
y-axis: {result}
</riskgraph>
WIKITEXT;

		$output = $this->parseWikitext( $wikitext );

		// Custom data attributes should be preserved for fixed parameters
		$this->assertStringContainsString( 'data-age="30"', $output );
		$this->assertStringContainsString( 'data-gender="male"', $output );
	}

	/**
	 * Test that each graph gets a unique ID
	 */
	public function testUniqueGraphIds() {
		$wikitext = <<<WIKITEXT
<riskgraph model="TestModel1">
x-axis: param
x-min: 0
x-max: 10
x-step: 1
y-axis: {result}
</riskgraph>

<riskgraph model="TestModel2">
x-axis: param
x-min: 0
x-max: 10
x-step: 1
y-axis: {result}
</riskgraph>
WIKITEXT;

		$output = $this->parseWikitext( $wikitext );

		// Should have two different IDs (using hash format: riskgraph-[hash])
		preg_match_all( '/id="riskgraph-([a-f0-9]+)"/', $output, $matches );
		$this->assertCount( 2, $matches[1], 'Should have two graph IDs' );
		$this->assertNotEquals( $matches[1][0], $matches[1][1], 'IDs should be unique' );
	}

	/**
	 * Test deferred rendering mechanism
	 */
	public function testDeferredRendering() {
		$wikitext = <<<WIKITEXT
<riskgraph model="TestModel">
x-axis: param
x-min: 0
x-max: 10
x-step: 1
y-axis: {result}
</riskgraph>
WIKITEXT;

		$parserOutput = $this->getParserOutput( $wikitext );

		// Should have deferred graph data in extension data
		$deferredGraphs = $parserOutput->getExtensionData( 'riskitools_deferred_graphs' );
		$this->assertIsArray( $deferredGraphs, 'Should have deferred graphs data' );
		$this->assertNotEmpty( $deferredGraphs, 'Should have at least one deferred graph' );
	}

	/**
	 * Helper to parse wikitext and return HTML
	 */
	private function parseWikitext( $wikitext ) {
		$parserOutput = $this->getParserOutput( $wikitext );
		return $parserOutput->getText();
	}

	/**
	 * Helper to get ParserOutput object
	 */
	private function getParserOutput( $wikitext ) {
		$parser = MediaWiki\MediaWikiServices::getInstance()->getParser();
		$title = Title::newFromText( 'TestPage' );
		$options = ParserOptions::newFromAnon();

		$parserOutput = $parser->parse( $wikitext, $title, $options );

		// Manually trigger ParserAfterTidy hook for deferred rendering
		$text = $parserOutput->getText();
		RiskiToolsHooks::onParserAfterTidy( $parser, $text );
		$parserOutput->setText( $text );

		return $parserOutput;
	}
}
