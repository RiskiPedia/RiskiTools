<?php

/**
 * @covers ApiRiskGraph
 * @group RiskiTools
 */
class ApiRiskGraphTest extends MediaWikiUnitTestCase {

	/**
	 * Test that parameter sweep generates correct data points
	 */
	public function testParameterSweepGeneration() {
		$result = ApiRiskGraph::generateParameterSweep( 0, 10, 2 );

		$expected = [ 0, 2, 4, 6, 8, 10 ];
		$this->assertSame( $expected, $result, 'Should generate correct sweep values' );
	}

	/**
	 * Test that step size must be positive
	 */
	public function testStepSizeValidation() {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Step size must be positive' );

		ApiRiskGraph::generateParameterSweep( 0, 10, 0 );
	}

	/**
	 * Test that step size can't be larger than range
	 */
	public function testStepSizeLargerThanRange() {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Step size cannot be larger than range' );

		ApiRiskGraph::generateParameterSweep( 0, 10, 20 );
	}

	/**
	 * Test that min must be less than max
	 */
	public function testMinMaxValidation() {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Minimum value must be less than maximum value' );

		ApiRiskGraph::generateParameterSweep( 10, 0, 1 );
	}

	/**
	 * Test that too many data points throws warning
	 */
	public function testMaxDataPointsLimit() {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Too many data points' );

		// This would generate 1001 points (0, 0.01, 0.02, ... 10.00)
		ApiRiskGraph::generateParameterSweep( 0, 10, 0.01 );
	}

	/**
	 * Test decimal step sizes work correctly
	 */
	public function testDecimalStepSize() {
		$result = ApiRiskGraph::generateParameterSweep( 0, 1, 0.25 );

		$expected = [ 0.0, 0.25, 0.5, 0.75, 1.0 ];
		$this->assertEqualsWithDelta( $expected, $result, 0.0001, 'Should handle decimal steps' );
	}

	/**
	 * Test negative values work
	 */
	public function testNegativeValues() {
		$result = ApiRiskGraph::generateParameterSweep( -5, 5, 2 );

		$expected = [ -5, -3, -1, 1, 3, 5 ];
		$this->assertSame( $expected, $result, 'Should handle negative values' );
	}

	/**
	 * Test that output format is correct
	 */
	public function testOutputFormat() {
		$output = ApiRiskGraph::formatGraphData(
			[ 0, 1, 2 ],
			[ 10, 20, 30 ],
			'Test Series'
		);

		$this->assertArrayHasKey( 'labels', $output );
		$this->assertArrayHasKey( 'datasets', $output );

		$this->assertSame( [ 0, 1, 2 ], $output['labels'] );
		$this->assertIsArray( $output['datasets'] );
		$this->assertCount( 1, $output['datasets'] );

		$dataset = $output['datasets'][0];
		$this->assertSame( 'Test Series', $dataset['label'] );
		$this->assertSame( [ 10, 20, 30 ], $dataset['data'] );
	}

	/**
	 * Test that invalid data lengths are caught
	 */
	public function testMismatchedDataLengths() {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Labels and data must have the same length' );

		ApiRiskGraph::formatGraphData(
			[ 0, 1, 2 ],
			[ 10, 20 ],  // Missing one value
			'Test'
		);
	}

	/**
	 * Test series parameter JSON parsing
	 */
	public function testSeriesParameterParsing() {
		$seriesJson = json_encode( [
			[ 'label' => 'Series A', 'yaxis' => '{result}', 'params' => [], 'color' => '#FF0000' ],
			[ 'label' => 'Series B', 'yaxis' => '{result}', 'params' => [], 'color' => null ]
		] );

		$series = ApiRiskGraph::parseSeriesParameter( $seriesJson );

		$this->assertIsArray( $series );
		$this->assertCount( 2, $series );
		$this->assertEquals( 'Series A', $series[0]['label'] );
		$this->assertEquals( '#FF0000', $series[0]['color'] );
	}

	/**
	 * Test series parameter with invalid JSON
	 */
	public function testSeriesParameterInvalidJson() {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Series must be valid JSON' );

		ApiRiskGraph::parseSeriesParameter( 'not valid json' );
	}

	/**
	 * Test series parameter with non-array JSON
	 */
	public function testSeriesParameterNotArray() {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Series must be an array' );

		ApiRiskGraph::parseSeriesParameter( json_encode( 'string' ) );
	}

	/**
	 * Test series parameter with empty array
	 */
	public function testSeriesParameterEmptyArray() {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Series array cannot be empty' );

		ApiRiskGraph::parseSeriesParameter( json_encode( [] ) );
	}

	/**
	 * Test series validation - missing required field (label)
	 */
	public function testSeriesValidationMissingLabel() {
		$series = [
			[ 'yaxis' => '{result}', 'params' => [], 'color' => null ]
		];

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Series 0: missing required field "label"' );

		ApiRiskGraph::validateSeriesDefinitions( $series );
	}

	/**
	 * Test series validation - missing required field (yaxis)
	 */
	public function testSeriesValidationMissingYaxis() {
		$series = [
			[ 'label' => 'Test', 'params' => [], 'color' => null ]
		];

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Series 0: missing required field "yaxis"' );

		ApiRiskGraph::validateSeriesDefinitions( $series );
	}

	/**
	 * Test series validation - too many series
	 */
	public function testSeriesValidationMaxLimit() {
		// Create 11 series (exceeds limit of 10)
		$series = [];
		for ( $i = 0; $i < 11; $i++ ) {
			$series[] = [ 'label' => "Series $i", 'yaxis' => '{result}', 'params' => [], 'color' => null ];
		}

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Maximum 10 series allowed' );

		ApiRiskGraph::validateSeriesDefinitions( $series );
	}

	/**
	 * Test series validation - valid series pass
	 */
	public function testSeriesValidationValid() {
		$series = [
			[ 'label' => 'Series A', 'yaxis' => '{result}', 'params' => [ 'x' => 1 ], 'color' => '#FF0000' ],
			[ 'label' => 'Series B', 'yaxis' => '{result}', 'params' => [], 'color' => null ]
		];

		// Should not throw exception
		$result = ApiRiskGraph::validateSeriesDefinitions( $series );
		$this->assertTrue( $result );
	}

	/**
	 * Test formatting multi-series data for Chart.js
	 */
	public function testFormatMultiSeriesData() {
		$labels = [ 0, 1, 2, 3 ];
		$seriesData = [
			[
				'label' => 'Series A',
				'data' => [ 10, 20, 30, 40 ],
				'color' => '#FF0000'
			],
			[
				'label' => 'Series B',
				'data' => [ 15, 25, 35, 45 ],
				'color' => null
			]
		];

		$result = ApiRiskGraph::formatMultiSeriesData( $labels, $seriesData );

		$this->assertArrayHasKey( 'labels', $result );
		$this->assertArrayHasKey( 'datasets', $result );
		$this->assertSame( $labels, $result['labels'] );
		$this->assertCount( 2, $result['datasets'] );

		// Check first series
		$this->assertSame( 'Series A', $result['datasets'][0]['label'] );
		$this->assertSame( [ 10, 20, 30, 40 ], $result['datasets'][0]['data'] );
		$this->assertSame( '#FF0000', $result['datasets'][0]['borderColor'] );
		$this->assertSame( '#FF0000', $result['datasets'][0]['backgroundColor'] );

		// Check second series (no color)
		$this->assertSame( 'Series B', $result['datasets'][1]['label'] );
		$this->assertSame( [ 15, 25, 35, 45 ], $result['datasets'][1]['data'] );
		$this->assertArrayNotHasKey( 'borderColor', $result['datasets'][1] );
		$this->assertArrayNotHasKey( 'backgroundColor', $result['datasets'][1] );
	}

	/**
	 * Test multi-series data with mismatched lengths
	 */
	public function testFormatMultiSeriesDataMismatchedLengths() {
		$labels = [ 0, 1, 2 ];
		$seriesData = [
			[
				'label' => 'Series A',
				'data' => [ 10, 20 ],  // Wrong length!
				'color' => null
			]
		];

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Series "Series A": data length (2) must match labels length (3)' );

		ApiRiskGraph::formatMultiSeriesData( $labels, $seriesData );
	}
}
