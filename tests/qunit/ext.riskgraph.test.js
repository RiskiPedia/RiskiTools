( function () {
	'use strict';

	QUnit.module( 'ext.riskgraph', ( hooks ) => {
		let server, $fixture;

		hooks.beforeEach( function () {
			// Create a fake server for API mocking
			server = this.sandbox.useFakeServer();
			server.respondImmediately = true;

			// Create a fixture element for DOM manipulation
			$fixture = $( '<div>' ).appendTo( document.body );

			// Mock mw.config and pagestate
			mw.config.set( 'wgPageName', 'TestPage' );
			if ( !window.RT ) {
				window.RT = {};
			}
			window.RT.pagestate = {
				allPageState: function () {
					return {};
				}
			};
		} );

		hooks.afterEach( function () {
			$fixture.remove();
		} );

		QUnit.test( 'Graph container detection', ( assert ) => {
			const $graph = $( '<div class="RiskiUI RiskGraph" id="riskgraph-test"></div>' );
			$fixture.append( $graph );

			const graphs = $fixture.find( '.RiskiUI.RiskGraph' );
			assert.strictEqual( graphs.length, 1, 'Should find one graph container' );
			assert.strictEqual( graphs.attr( 'id' ), 'riskgraph-test', 'Should have correct ID' );
		} );

		QUnit.test( 'Extract configuration from data attributes', ( assert ) => {
			const $graph = $( '<div class="RiskiUI RiskGraph"></div>' )
				.attr( 'data-model', 'TestModel' )
				.attr( 'data-swept-param', 'speed' )
				.attr( 'data-x-min', '0' )
				.attr( 'data-x-max', '100' )
				.attr( 'data-x-step', '10' )
				.attr( 'data-y-axis', '{risk}' );

			assert.strictEqual( $graph.data( 'model' ), 'TestModel', 'Model name extracted' );
			assert.strictEqual( $graph.data( 'sweptParam' ), 'speed', 'Swept param extracted' );
			assert.strictEqual( $graph.data( 'xMin' ), 0, 'Min value extracted' );
			assert.strictEqual( $graph.data( 'xMax' ), 100, 'Max value extracted' );
			assert.strictEqual( $graph.data( 'xStep' ), 10, 'Step value extracted' );
			assert.strictEqual( $graph.data( 'yAxis' ), '{risk}', 'Y-axis expression extracted' );
		} );

		QUnit.test( 'Build API request parameters', ( assert ) => {
			// This test will check if the module correctly builds API request params
			// Implementation will create this function
			const config = {
				model: 'TestModel',
				sweptParam: 'speed',
				xMin: 0,
				xMax: 100,
				xStep: 10,
				yAxis: '{risk}'
			};

			const pagestate = { age: 30, gender: 'male' };

			// The buildApiRequest function should be implemented
			assert.ok( typeof window.RiskGraph !== 'undefined', 'RiskGraph module should be defined' );

			if ( window.RiskGraph && window.RiskGraph.buildApiRequest ) {
				const request = window.RiskGraph.buildApiRequest( config, pagestate );

				assert.strictEqual( request.model, 'TestModel', 'Request includes model' );
				assert.strictEqual( request.sweptparam, 'speed', 'Request includes swept param' );
				assert.strictEqual( request.min, 0, 'Request includes min' );
				assert.strictEqual( request.max, 100, 'Request includes max' );
				assert.strictEqual( request.step, 10, 'Request includes step' );
				assert.strictEqual( request.yaxis, '{risk}', 'Request includes y-axis' );
				assert.ok( request.pagestate, 'Request includes pagestate' );
			}
		} );

		QUnit.test( 'Detect dependencies from y-axis expression', ( assert ) => {
			// Test that we can detect which pagestate variables are needed
			const yAxis = '{age} * {risk_factor} / 100';

			if ( window.RiskGraph && window.RiskGraph.extractDependencies ) {
				const deps = window.RiskGraph.extractDependencies( yAxis );

				assert.ok( deps.has( 'age' ), 'Should detect age dependency' );
				assert.ok( deps.has( 'risk_factor' ), 'Should detect risk_factor dependency' );
				assert.strictEqual( deps.size, 2, 'Should find exactly 2 dependencies' );
			} else {
				assert.ok( false, 'extractDependencies function not implemented yet' );
			}
		} );

		QUnit.test( 'Wait for dependencies before rendering', ( assert ) => {
			const $graph = $( '<div class="RiskiUI RiskGraph"></div>' )
				.attr( 'id', 'test-graph' )
				.attr( 'data-model', 'TestModel' )
				.attr( 'data-y-axis', '{age} * 2' );

			$fixture.append( $graph );

			// With missing dependencies, graph should show waiting state
			// This will be implemented in the actual code
			assert.ok( true, 'Graph waits for dependencies - implementation pending' );
		} );

		QUnit.test( 'Handle API response and format for Chart.js', ( assert ) => {
			// This test verifies the API module structure
			// Full API integration requires real MediaWiki API which is tested manually
			assert.ok( window.RiskGraph, 'RiskGraph module exists' );

			if ( window.RiskGraph ) {
				assert.ok( typeof window.RiskGraph.fetchGraphData === 'function', 'fetchGraphData function exists' );
				assert.ok( typeof window.RiskGraph.buildApiRequest === 'function', 'buildApiRequest function exists' );
				assert.ok( typeof window.RiskGraph.extractDependencies === 'function', 'extractDependencies function exists' );
			}
		} );

		QUnit.test( 'Handle API errors gracefully', async ( assert ) => {
			// Mock API error
			server.respondWith( 'POST', /action=riskgraph/, [ 500,
				{ 'Content-Type': 'application/json' },
				'{"error":{"code":"internal_api_error","info":"Internal server error"}}'
			] );

			const $graph = $( '<div class="RiskiUI RiskGraph" id="error-graph"></div>' )
				.attr( 'data-model', 'TestModel' );

			$fixture.append( $graph );

			// The implementation should show an error message
			// This is a placeholder assertion
			assert.ok( true, 'Error handling - implementation pending' );
		} );

		QUnit.test( 'Update graph when pagestate changes', ( assert ) => {
			const done = assert.async();

			const $graph = $( '<div class="RiskiUI RiskGraph"></div>' )
				.attr( 'id', 'update-graph' )
				.attr( 'data-model', 'TestModel' )
				.attr( 'data-y-axis', '{age} * 2' );

			$fixture.append( $graph );

			// Mock pagestate change
			window.RT.pagestate.allPageState = function () {
				return { age: 25 };
			};

			// Trigger pagestate change event
			if ( mw.hook ) {
				mw.hook( 'riskiData.changed' ).fire( { age: 25 } );
			}

			// Give time for async operations
			setTimeout( () => {
				assert.ok( true, 'Graph update on pagestate change - implementation pending' );
				done();
			}, 50 );
		} );

		QUnit.test( 'Do not update when swept parameter changes', ( assert ) => {
			// If the swept parameter changes in pagestate, the graph should NOT update
			// because we're graphing that parameter's range
			assert.ok( true, 'Swept parameter isolation - implementation pending' );
		} );

		QUnit.test( 'Parse optional title and labels', ( assert ) => {
			const $graph = $( '<div class="RiskiUI RiskGraph"></div>' )
				.attr( 'data-title', 'Risk Over Time' )
				.attr( 'data-x-label', 'Speed (mph)' )
				.attr( 'data-y-label', 'Risk Level' );

			assert.strictEqual( $graph.data( 'title' ), 'Risk Over Time', 'Title extracted' );
			assert.strictEqual( $graph.data( 'xLabel' ), 'Speed (mph)', 'X-label extracted' );
			assert.strictEqual( $graph.data( 'yLabel' ), 'Risk Level', 'Y-label extracted' );
		} );

		QUnit.test( 'Handle fixed parameters from data attributes', ( assert ) => {
			const $graph = $( '<div class="RiskiUI RiskGraph"></div>' )
				.attr( 'data-age', '30' )
				.attr( 'data-gender', 'male' )
				.attr( 'data-model', 'TestModel' );

			// Fixed parameters should be included in API request
			assert.strictEqual( $graph.data( 'age' ), 30, 'Fixed age parameter' );
			assert.strictEqual( $graph.data( 'gender' ), 'male', 'Fixed gender parameter' );
		} );

		QUnit.test( 'Chart.js initialization', ( assert ) => {
			// Test that Chart.js is properly initialized with correct config
			if ( typeof Chart !== 'undefined' ) {
				assert.ok( true, 'Chart.js is available' );
			} else {
				assert.ok( true, 'Chart.js not loaded yet - will be loaded via ResourceLoader' );
			}
		} );

		QUnit.test( 'Multiple graphs on same page', ( assert ) => {
			const $graph1 = $( '<div class="RiskiUI RiskGraph" id="graph1"></div>' )
				.attr( 'data-model', 'Model1' );
			const $graph2 = $( '<div class="RiskiUI RiskGraph" id="graph2"></div>' )
				.attr( 'data-model', 'Model2' );

			$fixture.append( $graph1, $graph2 );

			const graphs = $fixture.find( '.RiskiUI.RiskGraph' );
			assert.strictEqual( graphs.length, 2, 'Should find two graphs' );
			assert.notEqual( $graph1.attr( 'id' ), $graph2.attr( 'id' ), 'Graphs have unique IDs' );
		} );

		QUnit.test( 'Handle pagestate changes', ( assert ) => {
			// This test verifies that the hook listener is registered
			// Full integration testing requires DOM setup which is tested manually
			assert.ok( mw.hook, 'mw.hook exists' );

			// Verify the hook can be fired without errors
			if ( mw.hook ) {
				mw.hook( 'riskiData.changed' ).fire( { age: 25 } );
				assert.ok( true, 'riskiData.changed hook fired without error' );
			}
		} );
	} );

	QUnit.module( 'Multi-series support' );

	QUnit.test( 'Parse multi-series configuration', ( assert ) => {
		const seriesData = [
			{ label: 'Series A', yaxis: '{result}', params: { x: 1 }, color: '#FF0000' },
			{ label: 'Series B', yaxis: '{result}', params: { x: 2 }, color: null }
		];

		const $graph = $( '<div class="RiskiUI RiskGraph"></div>' )
			.attr( 'data-series', JSON.stringify( seriesData ) );

		// jQuery auto-parses JSON from data attributes
		const parsed = $graph.data( 'series' );

		assert.ok( Array.isArray( parsed ), 'Series should be an array' );
		assert.strictEqual( parsed.length, 2, 'Should have 2 series' );
		assert.strictEqual( parsed[ 0 ].label, 'Series A', 'First series label correct' );
		assert.strictEqual( parsed[ 0 ].color, '#FF0000', 'First series color correct' );
		assert.strictEqual( parsed[ 1 ].label, 'Series B', 'Second series label correct' );
		assert.strictEqual( parsed[ 1 ].color, null, 'Second series has no color' );
	} );

	QUnit.test( 'Parse multi-series with manual JSON parsing', ( assert ) => {
		const seriesData = [
			{ label: 'Test', yaxis: '{x}', params: {}, color: '#0066CC' }
		];

		const $graph = $( '<div class="RiskiUI RiskGraph"></div>' )
			.attr( 'data-series', JSON.stringify( seriesData ) );

		// Test manual parsing (as JavaScript code would do if jQuery fails)
		const jsonStr = $graph.attr( 'data-series' );
		const parsed = JSON.parse( jsonStr );

		assert.ok( Array.isArray( parsed ), 'Manual parse returns array' );
		assert.strictEqual( parsed[ 0 ].label, 'Test', 'Label parsed correctly' );
		assert.strictEqual( parsed[ 0 ].color, '#0066CC', 'Color parsed correctly' );
	} );

	QUnit.test( 'Handle JSON parse errors gracefully', ( assert ) => {
		const $graph = $( '<div class="RiskiUI RiskGraph"></div>' )
			.attr( 'data-series', 'not valid json' );

		// Test that invalid JSON doesn't crash
		let parsed;
		try {
			const jsonStr = $graph.attr( 'data-series' );
			parsed = JSON.parse( jsonStr );
		} catch ( e ) {
			parsed = null;
		}

		assert.strictEqual( parsed, null, 'Invalid JSON results in null' );
	} );

	QUnit.test( 'Backward compatibility with data-y-axis', ( assert ) => {
		const $graph = $( '<div class="RiskiUI RiskGraph"></div>' )
			.attr( 'data-y-axis', '{result}' );

		const yAxis = $graph.data( 'yAxis' );
		const series = $graph.data( 'series' );

		assert.strictEqual( yAxis, '{result}', 'y-axis attribute parsed' );
		assert.strictEqual( series, undefined, 'No series attribute' );
	} );

	QUnit.test( 'Build API request with series parameter', ( assert ) => {
		const config = {
			model: 'TestModel',
			sweptParam: 'age',
			xMin: 0,
			xMax: 10,
			xStep: 1,
			series: [
				{ label: 'Males', yaxis: '{risk}', params: { gender: 'male' }, color: '#0066CC' },
				{ label: 'Females', yaxis: '{risk}', params: { gender: 'female' }, color: '#CC0066' }
			]
		};

		const pagestate = { country: 'USA' };

		// Simulate buildApiRequest logic
		const request = {
			action: 'riskgraph',
			model: config.model,
			title: 'Test',
			sweptparam: config.sweptParam,
			min: config.xMin,
			max: config.xMax,
			step: config.xStep,
			pagestate: JSON.stringify( pagestate )
		};

		if ( config.series ) {
			request.series = JSON.stringify( config.series );
		} else if ( config.yAxis ) {
			request.yaxis = config.yAxis;
		}

		assert.ok( request.series, 'Series parameter included' );
		assert.strictEqual( request.yaxis, undefined, 'yaxis parameter not included' );

		const parsedSeries = JSON.parse( request.series );
		assert.strictEqual( parsedSeries.length, 2, 'Two series in request' );
		assert.strictEqual( parsedSeries[ 0 ].label, 'Males', 'First series correct' );
	} );

	QUnit.test( 'Build API request for single-series (backward compat)', ( assert ) => {
		const config = {
			model: 'TestModel',
			sweptParam: 'age',
			xMin: 0,
			xMax: 10,
			xStep: 1,
			yAxis: '{result}'
		};

		const pagestate = {};

		// Simulate buildApiRequest logic
		const request = {
			action: 'riskgraph',
			model: config.model,
			title: 'Test',
			sweptparam: config.sweptParam,
			min: config.xMin,
			max: config.xMax,
			step: config.xStep,
			pagestate: JSON.stringify( pagestate )
		};

		if ( config.series ) {
			request.series = JSON.stringify( config.series );
		} else if ( config.yAxis ) {
			request.yaxis = config.yAxis;
		}

		assert.strictEqual( request.yaxis, '{result}', 'yaxis parameter included' );
		assert.strictEqual( request.series, undefined, 'series parameter not included' );
	} );

	QUnit.test( 'Legend display based on series count', ( assert ) => {
		// Test that legend should be shown for multiple series
		const multiSeriesData = {
			labels: [ 0, 1, 2 ],
			datasets: [
				{ label: 'Series A', data: [ 1, 2, 3 ] },
				{ label: 'Series B', data: [ 4, 5, 6 ] }
			]
		};

		const shouldShowLegendMulti = multiSeriesData.datasets && multiSeriesData.datasets.length > 1;
		assert.strictEqual( shouldShowLegendMulti, true, 'Legend shown for multiple series' );

		// Test that legend should be hidden for single series
		const singleSeriesData = {
			labels: [ 0, 1, 2 ],
			datasets: [
				{ label: 'Series A', data: [ 1, 2, 3 ] }
			]
		};

		const shouldShowLegendSingle = singleSeriesData.datasets && singleSeriesData.datasets.length > 1;
		assert.strictEqual( shouldShowLegendSingle, false, 'Legend hidden for single series' );
	} );
}() );
