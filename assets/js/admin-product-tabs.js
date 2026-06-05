/**
 * Inner tabs on WooCommerce Product Designer panel.
 */
( function ( $ ) {
	'use strict';

	const panel = document.getElementById( 'wc_gpd_template_designer_panel' )
		|| document.getElementById( 'wc_gpd_product_designer_panel' );
	if ( ! panel ) {
		return;
	}

	const links = panel.querySelectorAll( '.wc-gpd-product-subtab-link' );
	const panels = panel.querySelectorAll( '.wc-gpd-product-subtab-panel' );

	function activate( targetId ) {
		links.forEach( ( link ) => {
			const active = link.getAttribute( 'href' ) === '#' + targetId;
			link.classList.toggle( 'is-active', active );
			link.setAttribute( 'aria-selected', active ? 'true' : 'false' );
		} );
		panels.forEach( ( subpanel ) => {
			subpanel.hidden = subpanel.id !== targetId;
		} );

		if ( 'wc_gpd_subtab_template' === targetId ) {
			setTimeout( () => window.dispatchEvent( new Event( 'resize' ) ), 80 );
		}
	}

	links.forEach( ( link ) => {
		link.addEventListener( 'click', ( event ) => {
			event.preventDefault();
			const target = ( link.getAttribute( 'href' ) || '' ).replace( '#', '' );
			if ( target ) {
				activate( target );
			}
		} );
	} );
}( jQuery ) );
