/**
 * PN Product Tabs: YIKES import runner (batches over admin-ajax, summed report).
 *
 * @package Pnscripts\ProductTabs
 */
( function () {
	'use strict';

	var cfg = window.pnscriptsProductTabsImport;
	var root = document.querySelector( '[data-pnscripts-pt-import]' );
	if ( ! cfg || ! root ) {
		return;
	}
	var buttons = root.querySelectorAll( '[data-pnscripts-pt-run]' );
	var status = root.querySelector( '[data-pnscripts-pt-status]' );
	var table = root.querySelector( '[data-pnscripts-pt-report]' );
	var notes = root.querySelector( '[data-pnscripts-pt-warnings]' );

	function emptyReport() {
		return { warnings: [] };
	}

	function merge( total, add ) {
		Object.keys( add ).forEach( function ( key ) {
			if ( 'warnings' === key ) {
				total.warnings = total.warnings.concat( add.warnings ).slice( 0, 200 );
			} else if ( 'number' === typeof add[ key ] ) {
				total[ key ] = ( total[ key ] || 0 ) + add[ key ];
			}
		} );
		return total;
	}

	function render( report, mode ) {
		var body = table.querySelector( 'tbody' );
		body.textContent = '';
		Object.keys( cfg.labels ).forEach( function ( key ) {
			var isUndo = 'undo' === mode;
			var undoKey = 'removed_globals' === key || 'removed_products' === key;
			if ( isUndo !== undoKey ) {
				return;
			}
			var tr = document.createElement( 'tr' );
			var th = document.createElement( 'th' );
			var td = document.createElement( 'td' );
			th.scope = 'row';
			th.textContent = cfg.labels[ key ];
			td.textContent = String( report[ key ] || 0 );
			tr.appendChild( th );
			tr.appendChild( td );
			body.appendChild( tr );
		} );
		table.classList.remove( 'hidden' );
		var list = notes.querySelector( 'ul' );
		list.textContent = '';
		( report.warnings || [] ).forEach( function ( text ) {
			var li = document.createElement( 'li' );
			li.textContent = text;
			list.appendChild( li );
		} );
		notes.classList.toggle( 'hidden', ! report.warnings || ! report.warnings.length );
	}

	function setBusy( busy ) {
		buttons.forEach( function ( button ) {
			button.disabled = busy;
		} );
	}

	function batch( mode, cursor, total, handled ) {
		var data = new window.FormData();
		data.append( 'action', cfg.action );
		data.append( 'nonce', cfg.nonce );
		data.append( 'mode', mode );
		data.append( 'cursor', String( cursor ) );
		return window.fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( json ) {
				if ( ! json || ! json.success ) {
					throw new Error( ( json && json.data && json.data.message ) || cfg.failed );
				}
				var result = json.data;
				total = merge( total, result.report );
				handled += ( result.report.products_seen || 0 ) + ( result.report.removed_products || 0 );
				status.textContent = cfg.running.replace( '%d', String( handled ) );
				render( total, mode );
				if ( result.done ) {
					return total;
				}
				return batch( mode, result.cursor, total, handled );
			} );
	}

	buttons.forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			var mode = button.getAttribute( 'data-pnscripts-pt-run' );
			if ( 'import' === mode && ! window.confirm( cfg.confirmImport ) ) {
				return;
			}
			if ( 'undo' === mode && ! window.confirm( cfg.confirmUndo ) ) {
				return;
			}
			setBusy( true );
			batch( mode, 0, emptyReport(), 0 )
				.then( function () {
					status.textContent = 'undo' === mode ? cfg.doneUndo : ( 'import' === mode ? cfg.doneImport : cfg.doneDry );
				} )
				.catch( function ( error ) {
					status.textContent = error && error.message ? error.message : cfg.failed;
				} )
				.then( function () {
					setBusy( false );
				} );
		} );
	} );
}() );
