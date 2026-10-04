/**
 * PN Product Tabs: product data panel (add, reorder, remove tabs; lazy editors).
 *
 * @package Pnscripts\ProductTabs
 */
( function ( $ ) {
	'use strict';

	var settings = window.pnscriptsProductTabs || {};
	var counter = Date.now();

	function editorSettings() {
		return {
			tinymce: {
				wpautop: true,
				plugins: 'charmap colorpicker hr lists paste tabfocus textcolor fullscreen wordpress wpautoresize wpeditimage wpemoji wpgallery wplink wptextpattern',
				toolbar1: 'formatselect,bold,italic,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,fullscreen',
				height: 220
			},
			quicktags: true,
			mediaButtons: true
		};
	}

	function initEditor( row ) {
		var textarea = row.find( '[data-pnscripts-pt-editor]' );
		if ( ! textarea.length || textarea.data( 'pnscriptsReady' ) || ! window.wp || ! wp.editor ) {
			return;
		}
		wp.editor.initialize( textarea.attr( 'id' ), editorSettings() );
		textarea.data( 'pnscriptsReady', true );
	}

	function removeEditor( row ) {
		var textarea = row.find( '[data-pnscripts-pt-editor]' );
		if ( ! textarea.length || ! textarea.data( 'pnscriptsReady' ) ) {
			return;
		}
		if ( window.tinymce && tinymce.get( textarea.attr( 'id' ) ) ) {
			tinymce.get( textarea.attr( 'id' ) ).save();
		}
		wp.editor.remove( textarea.attr( 'id' ) );
		textarea.data( 'pnscriptsReady', false );
	}

	function setOpen( row, open ) {
		row.toggleClass( 'is-open', open );
		row.find( '[data-pnscripts-pt-toggle]' ).attr( 'aria-expanded', open ? 'true' : 'false' );
		if ( open ) {
			initEditor( row );
		}
	}

	function refreshEmpty( panel ) {
		panel.find( '[data-pnscripts-pt-empty]' ).toggleClass( 'hidden', panel.find( '[data-pnscripts-pt-list] > li' ).length > 0 );
	}

	function addRow( panel, type, globalId, globalTitle ) {
		var template = document.getElementById( 'pnscripts-pt-template-' + type );
		if ( ! template ) {
			return null;
		}
		var index = String( counter++ );
		var html = template.innerHTML.split( '__INDEX__' ).join( index );
		var row = $( html );
		panel.find( '[data-pnscripts-pt-list]' ).append( row );
		if ( 'global' === type ) {
			row.find( '[data-pnscripts-pt-global-id]' ).val( globalId );
			row.find( '[data-pnscripts-pt-title]' ).text( globalTitle );
			row.removeClass( 'is-open' );
		} else {
			setOpen( row, true );
			row.find( '[data-pnscripts-pt-title-input]' ).trigger( 'focus' );
		}
		refreshEmpty( panel );
		return row;
	}

	$( function () {
		var panel = $( '#pnscripts_product_tabs_panel' );
		if ( ! panel.length ) {
			return;
		}
		var list = panel.find( '[data-pnscripts-pt-list]' );

		list.sortable( {
			handle: '.pnscripts-pt__handle',
			items: '> li',
			axis: 'y',
			placeholder: 'pnscripts-pt__placeholder',
			start: function ( event, ui ) {
				removeEditor( ui.item );
			},
			stop: function ( event, ui ) {
				if ( ui.item.hasClass( 'is-open' ) ) {
					initEditor( ui.item );
				}
			}
		} );

		panel.on( 'click', '[data-pnscripts-pt-toggle]', function () {
			var row = $( this ).closest( '[data-pnscripts-pt-row]' );
			setOpen( row, ! row.hasClass( 'is-open' ) );
		} );

		panel.on( 'click', '[data-pnscripts-pt-add]', function () {
			var type = $( this ).data( 'pnscriptsPtAdd' );
			if ( 'global' === type ) {
				var select = panel.find( '[data-pnscripts-pt-global-select]' );
				var option = select.find( 'option:selected' );
				if ( ! select.val() ) {
					select.trigger( 'focus' );
					return;
				}
				addRow( panel, 'global', select.val(), option.data( 'title' ) );
				select.val( '' );
				return;
			}
			addRow( panel, type );
		} );

		panel.on( 'click', '[data-pnscripts-pt-remove]', function () {
			if ( ! window.confirm( settings.confirmRemove || 'Remove?' ) ) {
				return;
			}
			var row = $( this ).closest( '[data-pnscripts-pt-row]' );
			removeEditor( row );
			row.remove();
			refreshEmpty( panel );
		} );

		panel.on( 'input', '[data-pnscripts-pt-title-input]', function () {
			var value = $( this ).val();
			$( this ).closest( '[data-pnscripts-pt-row]' ).find( '[data-pnscripts-pt-title]' ).text( value || settings.untitled || '' );
		} );

		panel.on( 'change', '[data-pnscripts-pt-enabled]', function () {
			$( this ).closest( '[data-pnscripts-pt-row]' ).toggleClass( 'is-disabled', ! this.checked );
		} );

		panel.on( 'click', '[data-pnscripts-pt-faq-add]', function () {
			var row = $( this ).closest( '[data-pnscripts-pt-row]' );
			var box = row.find( '[data-pnscripts-pt-faq]' );
			var next = parseInt( box.attr( 'data-next' ), 10 ) || box.children().length;
			var template = document.getElementById( 'pnscripts-pt-template-faq-item' );
			var html = template.innerHTML.split( '__INDEX__' ).join( row.data( 'index' ) ).split( '__FAQ__' ).join( String( next + 1000 ) );
			box.attr( 'data-next', next + 1 );
			var item = $( html );
			box.append( item );
			item.find( 'input' ).first().trigger( 'focus' );
		} );

		panel.on( 'click', '[data-pnscripts-pt-faq-remove]', function () {
			$( this ).closest( '[data-pnscripts-pt-faq-item]' ).remove();
		} );

		$( '#post' ).on( 'submit', function () {
			if ( window.tinymce ) {
				window.tinymce.triggerSave();
			}
		} );
	} );
}( jQuery ) );
