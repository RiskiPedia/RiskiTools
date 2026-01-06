/**
 * RiskGraph - Interactive graph rendering for RiskModels
 *
 * Depends on: Chart.js, ext.pagestate
 */
( function () {
	'use strict';

	// Create RiskGraph namespace
	window.RiskGraph = {

		/**
		 * Extract placeholder dependencies from an expression
		 * @param {string} expression Expression containing {placeholders}
		 * @return {Set} Set of placeholder names
		 */
		extractDependencies: function ( expression ) {
			const regex = /\{([a-zA-Z0-9_]+)\}/g;
			const deps = new Set();
			let match;

			while ( ( match = regex.exec( expression ) ) !== null ) {
				// Avoid matching MediaWiki templates {{...}}
				const doubleFirst = ( match.index > 0 ) && ( expression[ match.index - 1 ] === '{' );
				const doubleLast = ( match.index + match[ 0 ].length < expression.length ) &&
					( expression[ match.index + match[ 0 ].length ] === '}' );

				if ( !( doubleFirst && doubleLast ) ) {
					deps.add( match[ 1 ] );
				}
			}

			return deps;
		},

		/**
		 * Build API request parameters for riskgraph endpoint
		 * @param {Object} config Graph configuration
		 * @param {Object} pagestate Current page state
		 * @return {Object} API request parameters
		 */
		buildApiRequest: function ( config, pagestate ) {
			const request = {
				action: 'riskgraph',
				model: config.model,
				title: mw.config.get( 'wgPageName' ),
				sweptparam: config.sweptParam,
				min: config.xMin,
				max: config.xMax,
				step: config.xStep,
				pagestate: JSON.stringify( pagestate )
			};

			// Multi-series or single-series
			if ( config.series ) {
				request.series = JSON.stringify( config.series );
			} else {
				request.yaxis = config.yAxis;
			}

			return request;
		},

		/**
		 * Fetch graph data from API
		 * @param {Object} config Graph configuration
		 * @param {Object} pagestate Current page state
		 * @return {Promise} Resolves to Chart.js data format
		 */
		fetchGraphData: function ( config, pagestate ) {
			const params = this.buildApiRequest( config, pagestate );
			const api = new mw.Api();

			return api.postWithToken( 'csrf', params ).then( function ( data ) {
				if ( data && data.riskgraph ) {
					return data.riskgraph;
				}
				throw new Error( 'Invalid API response' );
			} );
		}
	};

	/**
	 * Manage individual graph instance
	 */
	function GraphManager( $element ) {
		this.$el = $element;
		this.chart = null;
		this.config = this.parseConfig();
		// For now, assume no external dependencies
		// The model defines its own parameters (via data- attributes) or uses swept parameter
		this.dependencies = new Set();
		this.lastStateStr = null;
	}

	/**
	 * Parse configuration from data attributes
	 */
	GraphManager.prototype.parseConfig = function () {
		// jQuery auto-parses JSON from data attributes, but handle edge cases
		let series = this.$el.data( 'series' );
		if ( typeof series === 'string' ) {
			try {
				series = JSON.parse( series );
			} catch ( e ) {
				console.error( 'Failed to parse series JSON:', e );
				series = null;
			}
		}

		return {
			model: this.$el.data( 'model' ),
			type: this.$el.data( 'type' ) || 'line',
			sweptParam: this.$el.data( 'sweptParam' ),
			xMin: parseFloat( this.$el.data( 'xMin' ) ),
			xMax: parseFloat( this.$el.data( 'xMax' ) ),
			xStep: parseFloat( this.$el.data( 'xStep' ) ),
			yAxis: this.$el.data( 'yAxis' ),
			series: series,
			title: this.$el.data( 'title' ),
			xLabel: this.$el.data( 'xLabel' ),
			yLabel: this.$el.data( 'yLabel' )
		};
	};

	/**
	 * Get fixed parameters from data attributes
	 */
	GraphManager.prototype.getFixedParams = function () {
		const fixed = {};
		const data = this.$el.data();

		for ( const key in data ) {
			if ( Object.prototype.hasOwnProperty.call( data, key ) ) {
				// Skip our special config keys and the graphManager itself
				if ( [ 'model', 'type', 'sweptParam', 'xMin', 'xMax', 'xStep',
					'yAxis', 'series', 'title', 'xLabel', 'yLabel', 'graphManager' ].indexOf( key ) === -1 ) {
					// Only include primitive values
					const val = data[ key ];
					if ( typeof val === 'string' || typeof val === 'number' || typeof val === 'boolean' ) {
						fixed[ key ] = val;
					}
				}
			}
		}

		return fixed;
	};

	/**
	 * Check if all dependencies are satisfied
	 */
	GraphManager.prototype.checkDependencies = function ( pagestate ) {
		const unresolved = [];

		for ( const dep of this.dependencies ) {
			if ( !( dep in pagestate ) ) {
				unresolved.push( dep );
			}
		}

		return unresolved;
	};

	/**
	 * Render or update the graph
	 */
	GraphManager.prototype.update = function () {
		const pagestate = window.RT && window.RT.pagestate ?
			window.RT.pagestate.allPageState() : {};

		// Merge in fixed parameters
		const fixedParams = this.getFixedParams();
		const mergedState = Object.assign( {}, pagestate, fixedParams );

		// Check dependencies
		const unresolved = this.checkDependencies( mergedState );

		if ( unresolved.length > 0 ) {
			this.showWaiting( unresolved );
			this.lastStateStr = null;
			return;
		}

		// Check if state has changed
		// Compare entire merged state (pagestate + fixed params)
		const currentStateStr = JSON.stringify( mergedState );

		if ( currentStateStr === this.lastStateStr ) {
			return; // No change
		}

		this.lastStateStr = currentStateStr;

		// Fetch and render
		this.showLoading();
		this.fetchAndRender( mergedState );
	};

	/**
	 * Show waiting state
	 */
	GraphManager.prototype.showWaiting = function ( unresolved ) {
		this.$el.html( '<p><i>Waiting for: ' + unresolved.join( ', ' ) + '</i></p>' );
	};

	/**
	 * Show loading state
	 */
	GraphManager.prototype.showLoading = function () {
		this.$el.html( '<p><i>Loading graph...</i></p>' );
	};

	/**
	 * Show error state
	 */
	GraphManager.prototype.showError = function ( message ) {
		this.$el.html( '<p class="error">Error: ' + mw.html.escape( message ) + '</p>' );
	};

	/**
	 * Fetch data and render chart
	 */
	GraphManager.prototype.fetchAndRender = function ( pagestate ) {
		const self = this;

		window.RiskGraph.fetchGraphData( this.config, pagestate )
			.then( function ( data ) {
				self.renderChart( data );
			} )
			.catch( function ( error ) {
				console.error( 'RiskGraph error:', error );
				console.error( 'Error details:', JSON.stringify( error ) );
				self.showError( error.message || error.error || 'Failed to load graph data' );
			} );
	};

	/**
	 * Render Chart.js chart
	 */
	GraphManager.prototype.renderChart = function ( data ) {
		// Ensure we have a canvas
		if ( !this.$el.find( 'canvas' ).length ) {
			this.$el.html( '<canvas></canvas>' );
		}

		const canvas = this.$el.find( 'canvas' )[ 0 ];
		const ctx = canvas.getContext( '2d' );

		// Destroy existing chart
		if ( this.chart ) {
			this.chart.destroy();
		}

		// Build Chart.js config
		const chartConfig = {
			type: this.config.type,
			data: data,
			options: {
				responsive: true,
				maintainAspectRatio: true,
				plugins: {
					title: {
						display: !!this.config.title,
						text: this.config.title
					},
					legend: {
						display: data.datasets && data.datasets.length > 1
					}
				},
				scales: {
					x: {
						display: true,
						title: {
							display: !!this.config.xLabel,
							text: this.config.xLabel
						}
					},
					y: {
						display: true,
						title: {
							display: !!this.config.yLabel,
							text: this.config.yLabel
						}
					}
				}
			}
		};

		// Create chart
		if ( typeof Chart !== 'undefined' ) {
			this.chart = new Chart( ctx, chartConfig );
		} else {
			this.showError( 'Chart.js not loaded' );
		}
	};

	/**
	 * Initialize all graphs on the page
	 */
	function initGraphs( $container ) {
		$container.find( '.RiskiUI.RiskGraph' ).each( function () {
			const $el = $( this );

			// Skip if already initialized
			if ( $el.data( 'graphManager' ) ) {
				return;
			}

			const manager = new GraphManager( $el );
			$el.data( 'graphManager', manager );

			// Initial render
			manager.update();
		} );
	}

	/**
	 * Update all graphs when pagestate changes
	 */
	function updateGraphs() {
		$( '.RiskiUI.RiskGraph' ).each( function () {
			const manager = $( this ).data( 'graphManager' );
			if ( manager ) {
				manager.update();
			}
		} );
	}

	// Hook into MediaWiki events
	mw.hook( 'wikipage.content' ).add( function ( $content ) {
		// Wait for Chart.js to load
		mw.loader.using( 'ext.riskgraph.chartjs' ).then( function () {
			initGraphs( $content );
		} );
	} );

	// Listen for pagestate changes
	mw.hook( 'riskiData.changed' ).add( updateGraphs );

}() );
