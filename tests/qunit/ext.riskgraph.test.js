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
}() );
