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
	 * Test parsing multiple series lines
	 */
	public function testMultiSeriesParsing() {
		$wikitext = <<<WIKITEXT
<riskgraph model="TestModel">
x-axis: age
x-min: 0
x-max: 10
x-step: 1
series: Males|{risk}|gender=male
series: Females|{risk}|gender=female
</riskgraph>
WIKITEXT;

		$output = $this->parseWikitext( $wikitext );

		// Debug: print the actual output
		// echo "\n=== RAW OUTPUT ===\n$output\n=== END OUTPUT ===\n";

		// Should contain data-series attribute with JSON
		$this->assertStringContainsString( 'data-series=', $output, 'Should have data-series attribute' );

		// Should NOT contain data-y-axis (only for single-series)
		$this->assertStringNotContainsString( 'data-y-axis=', $output, 'Should not have data-y-axis for multi-series' );

		// Extract and verify the JSON structure
		preg_match( '/data-series="([^"]*)"/', $output, $matches );
		$this->assertNotEmpty( $matches, 'Should find data-series attribute' );

		// Decode once (MediaWiki may still encode the attribute value)
		$seriesJson = html_entity_decode( $matches[1], ENT_QUOTES );
		$series = json_decode( $seriesJson, true );

		$this->assertIsArray( $series, 'Series data should be valid JSON array' );
		$this->assertCount( 2, $series, 'Should have 2 series' );

		// Verify first series
		$this->assertEquals( 'Males', $series[0]['label'] );
		$this->assertEquals( '{risk}', $series[0]['yaxis'] );
		$this->assertEquals( 'male', $series[0]['params']['gender'] );
		$this->assertNull( $series[0]['color'] );

		// Verify second series
		$this->assertEquals( 'Females', $series[1]['label'] );
		$this->assertEquals( '{risk}', $series[1]['yaxis'] );
		$this->assertEquals( 'female', $series[1]['params']['gender'] );
		$this->assertNull( $series[1]['color'] );
	}

	/**
	 * Test parsing series with color
	 */
	public function testSeriesParsingWithColor() {
		$wikitext = <<<WIKITEXT
<riskgraph model="TestModel">
x-axis: age
x-min: 0
x-max: 10
x-step: 1
series: Series A|{risk}|color=#FF0000
series: Series B|{risk}|color=#0000FF
</riskgraph>
WIKITEXT;

		$output = $this->parseWikitext( $wikitext );

		// Extract series JSON (MediaWiki may encode)
		preg_match( '/data-series="([^"]*)"/', $output, $matches );
		$seriesJson = html_entity_decode( $matches[1], ENT_QUOTES );
		$series = json_decode( $seriesJson, true );

		// Verify colors are parsed correctly
		$this->assertEquals( '#FF0000', $series[0]['color'], 'First series should have red color' );
		$this->assertEquals( '#0000FF', $series[1]['color'], 'Second series should have blue color' );
	}

	/**
	 * Test parsing series with multiple parameters
	 */
	public function testSeriesParsingWithParams() {
		$wikitext = <<<WIKITEXT
<riskgraph model="TestModel">
x-axis: age
x-min: 0
x-max: 10
x-step: 1
series: Test|{risk}|gender=male|age_multiplier=0.9|color=#FF0000
</riskgraph>
WIKITEXT;

		$output = $this->parseWikitext( $wikitext );

		// Extract series JSON (MediaWiki may encode)
		preg_match( '/data-series="([^"]*)"/', $output, $matches );
		$seriesJson = html_entity_decode( $matches[1], ENT_QUOTES );
		$series = json_decode( $seriesJson, true );

		// Verify parameters are parsed correctly
		$this->assertEquals( 'male', $series[0]['params']['gender'] );
		$this->assertEquals( '0.9', $series[0]['params']['age_multiplier'] );
		$this->assertEquals( '#FF0000', $series[0]['color'] );
	}

	/**
	 * Test malformed series line (missing yaxis)
	 */
	public function testMalformedSeriesLine() {
		$wikitext = <<<WIKITEXT
<riskgraph model="TestModel">
x-axis: age
x-min: 0
x-max: 10
x-step: 1
series: OnlyLabel
</riskgraph>
WIKITEXT;

		$output = $this->parseWikitext( $wikitext );

		// Should contain an error message
		$this->assertStringContainsString( 'error', strtolower( $output ), 'Should show error for malformed series' );
	}

	/**
	 * Test invalid color format
	 */
	public function testInvalidColorFormat() {
		$wikitext = <<<WIKITEXT
<riskgraph model="TestModel">
x-axis: age
x-min: 0
x-max: 10
x-step: 1
series: Test|{risk}|color=red
</riskgraph>
WIKITEXT;

		$output = $this->parseWikitext( $wikitext );

		// Should contain an error about invalid color format
		$this->assertStringContainsString( 'error', strtolower( $output ), 'Should show error for invalid color format' );
		$this->assertStringContainsString( 'color', strtolower( $output ), 'Error should mention color' );
	}

	/**
	 * Test error when neither series nor y-axis specified
	 */
	public function testMissingSeriesAndYAxis() {
		$wikitext = <<<WIKITEXT
<riskgraph model="TestModel">
x-axis: age
x-min: 0
x-max: 10
x-step: 1
</riskgraph>
WIKITEXT;

		$output = $this->parseWikitext( $wikitext );

		// Should contain an error about missing y-axis or series
		$this->assertStringContainsString( 'error', strtolower( $output ), 'Should show error when neither y-axis nor series specified' );
	}

	/**
	 * Test error when series params try to override swept parameter
	 */
	public function testSweptParameterInSeriesParams() {
		$wikitext = <<<WIKITEXT
<riskgraph model="TestModel">
x-axis: age
x-min: 0
x-max: 10
x-step: 1
series: Test|{risk}|age=30
</riskgraph>
WIKITEXT;

		$output = $this->parseWikitext( $wikitext );

		// Should contain an error about overriding swept parameter
		$this->assertStringContainsString( 'error', strtolower( $output ), 'Should show error when series params override swept parameter' );
	}

	/**
	 * Test backward compatibility with single-series y-axis format
	 */
	public function testBackwardCompatibilitySingleSeries() {
		$wikitext = <<<WIKITEXT
<riskgraph model="TestModel">
x-axis: age
x-min: 0
x-max: 10
x-step: 1
y-axis: {result}
</riskgraph>
WIKITEXT;

		$output = $this->parseWikitext( $wikitext );

		// Should work without error
		$this->assertStringNotContainsString( 'class="error"', $output, 'Old y-axis format should still work' );

		// Should have data-y-axis attribute (not data-series)
		$this->assertStringContainsString( 'data-y-axis=', $output, 'Should have data-y-axis for single-series' );
		$this->assertStringNotContainsString( 'data-series=', $output, 'Should not have data-series for single-series' );
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
