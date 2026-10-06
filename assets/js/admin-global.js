/**
 * PN Scripts Tabwise: global tab settings box.
 *
 * @package Pnscripts\ProductTabs
 */
( function ( $ ) {
	'use strict';

	$( function () {
		var box = $( '[data-pnscripts-pt-global]' );
		if ( ! box.length ) {
			return;
		}

		function sync() {
			var type = box.find( '[data-pnscripts-pt-type]:checked' ).val();
			var scope = box.find( '[data-pnscripts-pt-scope]:checked' ).val();
			box.find( '[data-pnscripts-pt-show-for]' ).each( function () {
				$( this ).toggle( $( this ).data( 'pnscriptsPtShowFor' ) === type );
			} );
			box.find( '[data-pnscripts-pt-show-for-scope]' ).each( function () {
				$( this ).toggle( $( this ).data( 'pnscriptsPtShowForScope' ) === scope );
			} );
		}

		box.on( 'change', '[data-pnscripts-pt-type], [data-pnscripts-pt-scope]', sync );
		sync();

		box.on( 'click', '[data-pnscripts-pt-faq-add]', function () {
			var list = box.find( '[data-pnscripts-pt-faq]' );
			var next = parseInt( list.attr( 'data-next' ), 10 ) || list.children().length;
			var template = document.getElementById( 'pnscripts-pt-template-global-faq' );
			list.attr( 'data-next', next + 1 );
			var item = $( template.innerHTML.split( '__FAQ__' ).join( String( next + 1000 ) ) );
			list.append( item );
			item.find( 'input' ).first().trigger( 'focus' );
		} );

		box.on( 'click', '[data-pnscripts-pt-faq-remove]', function () {
			$( this ).closest( '[data-pnscripts-pt-faq-item]' ).remove();
		} );

		$( document.body ).trigger( 'wc-enhanced-select-init' );
	} );
}( jQuery ) );
