( function () {
	'use strict';

	QUnit.module( 'ext.pagestate.urlhash', ( hooks ) => {
		let originalPagedata, originalUserChoiceKeys;

		hooks.beforeEach( function () {
			// Save original state
			originalPagedata = window.RT.pagedata;
			originalUserChoiceKeys = window.RT.userChoiceKeys;
			window.RT.pagedata = {};
			window.RT.userChoiceKeys = new Set();
			// Clear hash
			window.history.replaceState( null, '', window.location.pathname + window.location.search );
		} );

		hooks.afterEach( function () {
			// Restore original state
			window.RT.pagedata = originalPagedata;
			window.RT.userChoiceKeys = originalUserChoiceKeys;
			// Clear hash
			window.history.replaceState( null, '', window.location.pathname + window.location.search );
		} );

		// ========================================
		// parseHashState tests
		// ========================================

		QUnit.test( 'parseHashState: empty hash returns empty object', ( assert ) => {
			const result = RT.pagestate.parseHashState( '' );
			assert.deepEqual( result, {}, 'Empty string returns empty object' );

			const result2 = RT.pagestate.parseHashState( '#' );
			assert.deepEqual( result2, {}, 'Just # returns empty object' );

			const result3 = RT.pagestate.parseHashState();
			assert.deepEqual( result3, {}, 'Undefined returns empty object' );
		} );

		QUnit.test( 'parseHashState: single key-value pair', ( assert ) => {
			const result = RT.pagestate.parseHashState( '#age=25' );
			assert.deepEqual( result, { age: '25' }, 'Parses single pair' );
		} );

		QUnit.test( 'parseHashState: multiple key-value pairs', ( assert ) => {
			const result = RT.pagestate.parseHashState( '#age=25&name=test&rate=0.5' );
			assert.deepEqual( result, {
				age: '25',
				name: 'test',
				rate: '0.5'
			}, 'Parses multiple pairs' );
		} );

		QUnit.test( 'parseHashState: URL-decodes values', ( assert ) => {
			const result = RT.pagestate.parseHashState( '#desc=hello%20world&range=25-30' );
			assert.strictEqual( result.desc, 'hello world', 'Decodes %20 to space' );
			assert.strictEqual( result.range, '25-30', 'Preserves hyphens' );
		} );

		QUnit.test( 'parseHashState: URL-decodes keys', ( assert ) => {
			const result = RT.pagestate.parseHashState( '#my%20key=value' );
			assert.strictEqual( result[ 'my key' ], 'value', 'Decodes key with space' );
		} );

		QUnit.test( 'parseHashState: handles special characters', ( assert ) => {
			const result = RT.pagestate.parseHashState( '#special=%26%3D%23' );
			assert.strictEqual( result.special, '&=#', 'Decodes &, =, and #' );
		} );

		QUnit.test( 'parseHashState: skips entries with empty keys', ( assert ) => {
			const result = RT.pagestate.parseHashState( '#valid=yes&=nokey&good=ok' );
			assert.strictEqual( result.valid, 'yes', 'Valid pair parsed' );
			assert.strictEqual( result.good, 'ok', 'Second valid pair parsed' );
			assert.ok( !( '' in result ), 'Empty key skipped' );
		} );

		QUnit.test( 'parseHashState: allows empty values', ( assert ) => {
			const result = RT.pagestate.parseHashState( '#empty=&full=value' );
			assert.strictEqual( result.empty, '', 'Empty value is allowed' );
			assert.strictEqual( result.full, 'value', 'Non-empty value parsed' );
		} );

		QUnit.test( 'parseHashState: handles entries without equals sign', ( assert ) => {
			const result = RT.pagestate.parseHashState( '#valid=yes&noequals&good=ok' );
			assert.strictEqual( result.valid, 'yes', 'Valid pair before parsed' );
			assert.strictEqual( result.good, 'ok', 'Valid pair after parsed' );
			assert.ok( !( 'noequals' in result ), 'Entry without = skipped' );
		} );

		QUnit.test( 'parseHashState: handles value with equals sign', ( assert ) => {
			const result = RT.pagestate.parseHashState( '#equation=a=b' );
			assert.strictEqual( result.equation, 'a=b', 'Value can contain =' );
		} );

		// ========================================
		// setUserChoice / setUserChoices tests
		// ========================================

		QUnit.test( 'setUserChoice: sets pagestate and marks as user choice', ( assert ) => {
			RT.pagestate.setUserChoice( 'mykey', 'myvalue' );

			assert.strictEqual( RT.pagestate.getPageState( 'mykey' ), 'myvalue', 'Value set in pagestate' );
			assert.ok( RT.pagestate.isUserChoice( 'mykey' ), 'Key marked as user choice' );
		} );

		QUnit.test( 'setUserChoices: sets multiple values and marks all as user choices', ( assert ) => {
			RT.pagestate.setUserChoices( { key1: 'val1', key2: 'val2' } );

			assert.strictEqual( RT.pagestate.getPageState( 'key1' ), 'val1', 'First value set' );
			assert.strictEqual( RT.pagestate.getPageState( 'key2' ), 'val2', 'Second value set' );
			assert.ok( RT.pagestate.isUserChoice( 'key1' ), 'First key marked as user choice' );
			assert.ok( RT.pagestate.isUserChoice( 'key2' ), 'Second key marked as user choice' );
		} );

		QUnit.test( 'setUserChoice: updates URL hash', ( assert ) => {
			RT.pagestate.setUserChoice( 'testkey', 'testvalue' );

			const hash = window.location.hash;
			assert.ok( hash.includes( 'testkey=testvalue' ), 'Hash updated after setUserChoice' );
		} );

		QUnit.test( 'setUserChoices: updates URL hash with multiple values', ( assert ) => {
			RT.pagestate.setUserChoices( { key1: 'val1', key2: 'val2' } );

			const hash = window.location.hash;
			assert.ok( hash.includes( 'key1=val1' ), 'Hash contains key1' );
			assert.ok( hash.includes( 'key2=val2' ), 'Hash contains key2' );
		} );

		// ========================================
		// setPageState does NOT update URL
		// ========================================

		QUnit.test( 'setPageState: does NOT update URL hash', ( assert ) => {
			RT.pagestate.setPageState( 'programmatic', 'value' );

			const hash = window.location.hash;
			assert.ok( !hash.includes( 'programmatic' ), 'Hash NOT updated by setPageState' );
			assert.ok( !RT.pagestate.isUserChoice( 'programmatic' ), 'Key NOT marked as user choice' );
		} );

		QUnit.test( 'setPageStates: does NOT update URL hash', ( assert ) => {
			RT.pagestate.setPageStates( { prog1: 'a', prog2: 'b' } );

			const hash = window.location.hash;
			assert.ok( !hash.includes( 'prog1' ), 'Hash does not contain prog1' );
			assert.ok( !hash.includes( 'prog2' ), 'Hash does not contain prog2' );
		} );

		// ========================================
		// getShareableURL tests
		// ========================================

		QUnit.test( 'getShareableURL: only includes user choice keys', ( assert ) => {
			// Set programmatic state (should NOT appear in URL)
			RT.pagestate.setPageStates( { internal: 'hidden', config: 'secret' } );

			// Set user choices (SHOULD appear in URL)
			RT.pagestate.setUserChoices( { age: '25', rate: '0.5' } );

			const url = RT.pagestate.getShareableURL();

			assert.ok( url.includes( 'age=25' ), 'User choice age included' );
			assert.ok( url.includes( 'rate=0.5' ), 'User choice rate included' );
			assert.ok( !url.includes( 'internal' ), 'Programmatic key excluded' );
			assert.ok( !url.includes( 'config' ), 'Programmatic key excluded' );
		} );

		QUnit.test( 'getShareableURL: empty when no user choices', ( assert ) => {
			RT.pagestate.setPageStates( { internal: 'value' } );

			const url = RT.pagestate.getShareableURL();
			assert.ok( !url.includes( '#' ) || url.endsWith( '#' ), 'No meaningful hash' );
		} );

		QUnit.test( 'getShareableURL: URL-encodes special characters', ( assert ) => {
			RT.pagestate.setUserChoices( { desc: 'hello world', amp: 'a&b' } );

			const url = RT.pagestate.getShareableURL();
			assert.ok( url.includes( 'desc=hello%20world' ), 'Space encoded' );
			assert.ok( url.includes( 'amp=a%26b' ), 'Ampersand encoded' );
		} );

		QUnit.test( 'getShareableURL: skips non-scalar user choice values', ( assert ) => {
			// Manually add an object to pagedata and mark as user choice
			// (shouldn't happen in practice, but defensive test)
			window.RT.pagedata.obj = { nested: 'value' };
			window.RT.userChoiceKeys.add( 'obj' );
			RT.pagestate.setUserChoice( 'scalar', '123' );

			const url = RT.pagestate.getShareableURL();
			assert.ok( url.includes( 'scalar=123' ), 'Scalar included' );
			assert.ok( !url.includes( 'obj=' ), 'Object skipped' );
		} );

		QUnit.test( 'getShareableURL: roundtrip - parse generated URL', ( assert ) => {
			RT.pagestate.setUserChoices( {
				age: '25',
				name: 'test user',
				rate: '0.5'
			} );

			const url = RT.pagestate.getShareableURL();
			const hash = url.substring( url.indexOf( '#' ) );
			const parsed = RT.pagestate.parseHashState( hash );

			assert.deepEqual( parsed, {
				age: '25',
				name: 'test user',
				rate: '0.5'
			}, 'Roundtrip preserves values' );
		} );

		// ========================================
		// Delete behavior
		// ========================================

		QUnit.test( 'deletePageState: removes user choice from URL', ( assert ) => {
			RT.pagestate.setUserChoices( { keep: 'yes', remove: 'no' } );
			assert.ok( window.location.hash.includes( 'remove=no' ), 'Key present before delete' );

			RT.pagestate.deletePageState( 'remove' );

			const hash = window.location.hash;
			assert.ok( hash.includes( 'keep=yes' ), 'Kept key still in hash' );
			assert.ok( !hash.includes( 'remove' ), 'Deleted key removed from hash' );
			assert.ok( !RT.pagestate.isUserChoice( 'remove' ), 'Key no longer marked as user choice' );
		} );

		QUnit.test( 'deletePageStates: removes multiple user choices from URL', ( assert ) => {
			RT.pagestate.setUserChoices( { a: '1', b: '2', c: '3' } );

			RT.pagestate.deletePageStates( [ 'a', 'b' ] );

			const hash = window.location.hash;
			assert.ok( !hash.includes( 'a=' ), 'Key a removed' );
			assert.ok( !hash.includes( 'b=' ), 'Key b removed' );
			assert.ok( hash.includes( 'c=3' ), 'Key c preserved' );
		} );

		QUnit.test( 'hash cleared when all user choices deleted', ( assert ) => {
			RT.pagestate.setUserChoice( 'only', 'one' );
			RT.pagestate.deletePageState( 'only' );

			const hash = window.location.hash;
			assert.ok( hash === '' || hash === '#', 'Hash empty when no user choices' );
		} );

		// ========================================
		// loadFromHash tests
		// ========================================

		QUnit.test( 'loadFromHash: populates pagedata and marks as user choices', ( assert ) => {
			window.history.replaceState( null, '', '#preload=value&num=42' );

			RT.pagestate.loadFromHash();

			assert.strictEqual( RT.pagestate.getPageState( 'preload' ), 'value', 'preload key loaded' );
			assert.strictEqual( RT.pagestate.getPageState( 'num' ), '42', 'num key loaded' );
			assert.ok( RT.pagestate.isUserChoice( 'preload' ), 'preload marked as user choice' );
			assert.ok( RT.pagestate.isUserChoice( 'num' ), 'num marked as user choice' );
		} );

		QUnit.test( 'loadFromHash: hash values overwrite existing pagedata', ( assert ) => {
			window.RT.pagedata = { existing: 'original' };

			window.history.replaceState( null, '', '#existing=fromhash&newkey=new' );

			RT.pagestate.loadFromHash();

			assert.strictEqual( RT.pagestate.getPageState( 'existing' ), 'fromhash', 'Hash overwrites existing' );
			assert.strictEqual( RT.pagestate.getPageState( 'newkey' ), 'new', 'New key added' );
		} );

		// ========================================
		// isUserChoice API
		// ========================================

		QUnit.test( 'isUserChoice: returns false for programmatic keys', ( assert ) => {
			RT.pagestate.setPageState( 'programmatic', 'value' );

			assert.ok( !RT.pagestate.isUserChoice( 'programmatic' ), 'Programmatic key is not user choice' );
		} );

		QUnit.test( 'isUserChoice: returns true for user choice keys', ( assert ) => {
			RT.pagestate.setUserChoice( 'userchoice', 'value' );

			assert.ok( RT.pagestate.isUserChoice( 'userchoice' ), 'User choice key identified' );
		} );

		QUnit.test( 'isUserChoice: returns false for unknown keys', ( assert ) => {
			assert.ok( !RT.pagestate.isUserChoice( 'nonexistent' ), 'Unknown key is not user choice' );
		} );

	} );

}() );
