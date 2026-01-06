<?php

use MediaWiki\MediaWikiServices;

/**
 * API module for generating risk graph data
 *
 * This module sweeps a parameter across a range and calculates
 * RiskModel output at each point to generate chart data.
 */
class ApiRiskGraph extends ApiBase {

	/**
	 * Maximum number of data points allowed in a graph
	 */
	private const MAX_DATA_POINTS = 1000;

	/**
	 * Generate an array of parameter values from min to max by step
	 *
	 * @param float $min Minimum value (inclusive)
	 * @param float $max Maximum value (inclusive)
	 * @param float $step Step size
	 * @return array Array of numeric values
	 * @throws InvalidArgumentException If parameters are invalid
	 */
	public static function generateParameterSweep( $min, $max, $step ) {
		// Validate inputs
		if ( $min >= $max ) {
			throw new InvalidArgumentException( 'Minimum value must be less than maximum value' );
		}

		if ( $step <= 0 ) {
			throw new InvalidArgumentException( 'Step size must be positive' );
		}

		$range = $max - $min;
		if ( $step > $range ) {
			throw new InvalidArgumentException( 'Step size cannot be larger than range' );
		}

		// Check if we'll generate too many points
		$estimatedPoints = ( $range / $step ) + 1;
		if ( $estimatedPoints > self::MAX_DATA_POINTS ) {
			throw new InvalidArgumentException(
				'Too many data points would be generated (' . ceil( $estimatedPoints ) .
				'). Maximum is ' . self::MAX_DATA_POINTS . '. Increase step size.'
			);
		}

		// Generate the sweep values
		$values = [];
		$current = $min;

		// Use a small epsilon for floating point comparison
		$epsilon = $step / 1000;

		while ( $current <= $max + $epsilon ) {
			$values[] = $current;
			$current += $step;
		}

		// Ensure the last value is exactly max if we're close enough
		$lastValue = end( $values );
		if ( abs( $lastValue - $max ) < $epsilon && $lastValue !== $max ) {
			array_pop( $values );
			$values[] = $max;
		}

		return $values;
	}

	/**
	 * Format graph data in Chart.js compatible format
	 *
	 * @param array $labels X-axis labels (parameter values)
	 * @param array $data Y-axis data points (calculated results)
	 * @param string $seriesLabel Label for the data series
	 * @return array Formatted data structure for Chart.js
	 * @throws InvalidArgumentException If array lengths don't match
	 */
	public static function formatGraphData( array $labels, array $data, $seriesLabel ) {
		if ( count( $labels ) !== count( $data ) ) {
			throw new InvalidArgumentException( 'Labels and data must have the same length' );
		}

		return [
			'labels' => $labels,
			'datasets' => [
				[
					'label' => $seriesLabel,
					'data' => $data
				]
			]
		];
	}

	/**
	 * Format multi-series graph data in Chart.js compatible format
	 *
	 * @param array $labels X-axis labels (parameter values)
	 * @param array $seriesData Array of series with label, data, and optional color
	 * @return array Formatted data structure for Chart.js
	 * @throws InvalidArgumentException If data lengths don't match
	 */
	public static function formatMultiSeriesData( array $labels, array $seriesData ) {
		$datasets = [];

		foreach ( $seriesData as $series ) {
			$seriesLabel = $series['label'];
			$data = $series['data'];
			$color = $series['color'];

			// Validate data length matches labels
			if ( count( $data ) !== count( $labels ) ) {
				throw new InvalidArgumentException(
					sprintf(
						'Series "%s": data length (%d) must match labels length (%d)',
						$seriesLabel,
						count( $data ),
						count( $labels )
					)
				);
			}

			// Build dataset
			$dataset = [
				'label' => $seriesLabel,
				'data' => $data
			];

			// Add color if specified
			if ( $color !== null ) {
				$dataset['borderColor'] = $color;
				$dataset['backgroundColor'] = $color;
			}

			$datasets[] = $dataset;
		}

		return [
			'labels' => $labels,
			'datasets' => $datasets
		];
	}

	/**
	 * Parse and validate series parameter from JSON string
	 *
	 * @param string $seriesJson JSON string containing series definitions
	 * @return array Parsed series array
	 * @throws InvalidArgumentException If JSON is invalid or malformed
	 */
	public static function parseSeriesParameter( $seriesJson ) {
		$series = json_decode( $seriesJson, true );

		if ( $series === null ) {
			throw new InvalidArgumentException( 'Series must be valid JSON' );
		}

		if ( !is_array( $series ) ) {
			throw new InvalidArgumentException( 'Series must be an array' );
		}

		if ( empty( $series ) ) {
			throw new InvalidArgumentException( 'Series array cannot be empty' );
		}

		return $series;
	}

	/**
	 * Validate series definitions array
	 *
	 * @param array $series Array of series definitions
	 * @return bool True if validation passes
	 * @throws InvalidArgumentException If validation fails
	 */
	public static function validateSeriesDefinitions( array $series ) {
		// Check max limit
		if ( count( $series ) > 10 ) {
			throw new InvalidArgumentException( 'Maximum 10 series allowed, got ' . count( $series ) );
		}

		// Validate each series has required fields
		foreach ( $series as $idx => $seriesDef ) {
			if ( !isset( $seriesDef['label'] ) ) {
				throw new InvalidArgumentException( "Series $idx: missing required field \"label\"" );
			}

			if ( !isset( $seriesDef['yaxis'] ) ) {
				throw new InvalidArgumentException( "Series $idx: missing required field \"yaxis\"" );
			}
		}

		return true;
	}

	/**
	 * Execute the API request
	 */
	public function execute() {
		// Get parameters
		$params = $this->extractRequestParams();

		$modelName = $params['model'];
		$sweptParam = $params['sweptparam'];
		$min = floatval( $params['min'] );
		$max = floatval( $params['max'] );
		$step = floatval( $params['step'] );
		$seriesParam = $params['series'] ?? null;
		$yaxis = $params['yaxis'] ?? null;
		$pagestate = $params['pagestate'] ? json_decode( $params['pagestate'], true ) : [];

		try {
			// Determine mode: multi-series or single-series
			if ( $seriesParam ) {
				// Multi-series mode
				$seriesDefinitions = self::parseSeriesParameter( $seriesParam );
				self::validateSeriesDefinitions( $seriesDefinitions );
			} else {
				// Single-series mode (backward compatibility)
				if ( !$yaxis ) {
					$this->dieWithError( 'Either yaxis or series parameter is required', 'missing-yaxis' );
				}
				$seriesDefinitions = [[
					'label' => trim( $yaxis, '{}' ),
					'yaxis' => $yaxis,
					'params' => [],
					'color' => null
				]];
			}

			// Generate parameter sweep
			$xValues = self::generateParameterSweep( $min, $max, $step );

			// Fetch the model
			$pageTitle = $params['title'] ?? '';
			$title = Title::newFromText( $pageTitle );
			if ( !$title ) {
				$title = Title::newMainPage(); // Fallback
			}

			$model = \RiskiToolsHooks::fetchRiskModel( $modelName, $pageTitle, null );
			if ( !$model ) {
				$this->dieWithError( "RiskModel '$modelName' not found", 'model-not-found' );
			}

			// Get model parameters
			$dbParams = \RiskiToolsHooks::fetchRiskModelParams( $model['rm_id'], $model );

			// Generate data for each series
			$seriesData = [];
			$startTime = microtime( true );
			$maxExecutionTime = 5; // 5 seconds timeout

			foreach ( $seriesDefinitions as $series ) {
				$yValues = [];

				// Merge: pagestate + series-specific fixed params
				$seriesPagestate = empty( $series['params'] )
					? $pagestate
					: array_merge( $pagestate, $series['params'] );

				foreach ( $xValues as $xValue ) {
					// Check timeout
					if ( microtime( true ) - $startTime > $maxExecutionTime ) {
						$this->dieWithError( 'Graph generation timeout', 'timeout' );
					}

					// For this series: pagestate + series params + swept param
					$currentPagestate = array_merge(
						$seriesPagestate,
						[ $sweptParam => $xValue ]
					);

					// Resolve all parameters
					$resolvedParams = self::resolveParameters( $model['rm_text'], $dbParams, $currentPagestate, $title );

					// Extract the y-axis value (strip {})
					$yaxisVar = trim( $series['yaxis'], '{}' );
					$yValue = $resolvedParams[$yaxisVar] ?? null;
					if ( $yValue === null ) {
						$this->dieWithError(
							"Variable $yaxisVar not found in series '{$series['label']}' at x=$xValue",
							'missing-variable'
						);
					}

					// Convert to number
					$yValues[] = floatval( strip_tags( $yValue ) );
				}

				$seriesData[] = [
					'label' => $series['label'],
					'data' => $yValues,
					'color' => $series['color']
				];
			}

			// Format data for Chart.js
			$chartData = self::formatMultiSeriesData( $xValues, $seriesData );

			// Return result
			$this->getResult()->addValue( null, 'riskgraph', $chartData );

		} catch ( Exception $e ) {
			$this->dieWithError( $e->getMessage(), 'exception' );
		}
	}

	/**
	 * Resolve parameters by parsing expressions through MediaWiki parser
	 * Uses MediaWiki parser to evaluate expressions safely (no eval!)
	 */
	private static function resolveParameters( $modelText, $dbParams, $pagestate, $title ) {
		// Sort only the model parameters by dependencies
		// Pagestate values are already resolved, don't include them in the sort
		$sortResult = \RiskiToolsHooks::topologicalSortParameters( $dbParams );
		if ( $sortResult['error'] ) {
			throw new Exception( $sortResult['error'] );
		}

		$resolvedParams = [];
		$options = ParserOptions::newFromAnon();

		// Resolve each model parameter in sorted order
		foreach ( $sortResult['sorted'] as $name ) {
			$expression = $dbParams[$name];

			// Substitute already-resolved model parameters
			foreach ( $resolvedParams as $key => $value ) {
				$expression = str_replace( '{' . $key . '}', $value, $expression );
			}

			// Substitute pagestate parameters (which are already resolved values)
			foreach ( $pagestate as $key => $value ) {
				$expression = str_replace( '{' . $key . '}', $value, $expression );
			}

			// Parse through MediaWiki parser to evaluate {{#expr:}} and other parser functions
			// Use the same pattern as ApiRiskParse
			$parser = MediaWikiServices::getInstance()->getParser();
			$parsed = $parser->parse( $expression, $title, $options, true, true );
			$result = trim( strip_tags( $parsed->getText( [ 'unwrap' => true ] ) ) );

			// Store the resolved value
			$resolvedParams[$name] = $result;
		}

		return $resolvedParams;
	}

	/**
	 * Define allowed parameters
	 */
	public function getAllowedParams() {
		return [
			'model' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			],
			'title' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => false,
			],
			'pagestate' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => false,
			],
			'sweptparam' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			],
			'min' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			],
			'max' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			],
			'step' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			],
			'yaxis' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => false,  // Optional if series provided
			],
			'series' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => false,  // JSON string
			],
		];
	}

	/**
	 * Don't require CSRF token for read-only API
	 */
	public function needsToken() {
		return false;
	}

	/**
	 * This is a read-only API
	 */
	public function isWriteMode() {
		return false;
	}
}
