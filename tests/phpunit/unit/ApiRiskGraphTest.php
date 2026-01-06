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
}
